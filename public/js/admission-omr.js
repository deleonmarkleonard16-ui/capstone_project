// OMR v2 — Dynamic item count support.
// Coordinates are derived from the SVG layout in admin/admission/paper.blade.php.
// The projective transform maps webcam/image pixel coords → SVG viewBox coords.
// SVG layout constants (must stay in sync with paper.blade.php):
//   colStartX  = 40 + col * 260
//   bubbleX    = colStartX + 85 + optionIndex * 36
//   rowY (first item in col) = 54, step = 21
//   4 corner markers at: TL(20,160), TR(1080,160), BR(1080,620), BL(20,620)
//   (these are the approximate midpoints of the markers in scan space)

export function projectiveMap(corners) {
    const source = [[20, 160], [1080, 160], [1080, 620], [20, 620]];
    const matrix = [];
    source.forEach(([x, y], i) => {
        const [u, v] = corners[i];
        matrix.push([x, y, 1, 0, 0, 0, -u*x, -u*y, u]);
        matrix.push([0, 0, 0, x, y, 1, -v*x, -v*y, v]);
    });
    for (let col = 0; col < 8; col++) {
        let pivot = col;
        for (let row = col + 1; row < 8; row++) if (Math.abs(matrix[row][col]) > Math.abs(matrix[pivot][col])) pivot = row;
        [matrix[col], matrix[pivot]] = [matrix[pivot], matrix[col]];
        const divisor = matrix[col][col];
        if (Math.abs(divisor) < 1e-8) throw new Error('Invalid marker positions. Select four distinct corners in order.');
        for (let k = col; k <= 8; k++) matrix[col][k] /= divisor;
        for (let row = 0; row < 8; row++) {
            if (row === col) continue;
            const factor = matrix[row][col];
            for (let k = col; k <= 8; k++) matrix[row][k] -= factor * matrix[col][k];
        }
    }
    const h = matrix.map(row => row[8]);
    return (x, y) => {
        const d = h[6]*x + h[7]*y + 1;
        return [(h[0]*x+h[1]*y+h[2])/d, (h[3]*x+h[4]*y+h[5])/d];
    };
}

/**
 * Detect shaded bubble answers from a captured image.
 *
 * @param {ImageData} image      - Pixel data from canvas.getImageData()
 * @param {number[][]} corners   - Four [x,y] corner marker points (TL,TR,BR,BL clockwise)
 * @param {number} totalItems    - Total exam items (default 80, matches active cycle total_items)
 * @returns {Array<{item,answer,fills,reason}>}
 */
export function detectAnswers(image, corners, totalItems = 80) {
    // Reject reversed, crossing, or severely compressed quadrilaterals.
    const turns = corners.map((p, i) => {
        const q = corners[(i+1)%4], r = corners[(i+2)%4];
        return (q[0]-p[0])*(r[1]-q[1])-(q[1]-p[1])*(r[0]-q[0]);
    });
    if (turns.some(turn => turn < 100)) throw new Error('Markers must surround an upright sheet in clockwise order.');
    const project = projectiveMap(corners);
    const gray = (x, y) => {
        const [u, v] = project(x, y).map(Math.round);
        if (u < 0 || v < 0 || u >= image.width || v >= image.height) throw new Error('The entire answer matrix must be visible.');
        const offset = (v*image.width+u)*4;
        return .299*image.data[offset]+.587*image.data[offset+1]+.114*image.data[offset+2];
    };

    // ── Dynamic column/row layout (mirrors paper.blade.php SVG math) ──
    const COLS        = 4;
    const rowsPerCol  = Math.ceil(totalItems / COLS);  // e.g. 80→20, 100→25, 120→30, 144→36

    return Array.from({length: totalItems}, (_, index) => {
        // Which column and row within that column?
        const column = Math.floor(index / rowsPerCol);
        const row    = index % rowsPerCol;

        // SVG coordinates (must match paper.blade.php colStartX + bubbleX formula)
        // colStartX = 40 + column * 260; bubbleX = colStartX + 85 + optionIndex * 36
        // rowY = 54 + row * 21
        const colStartX = 40 + column * 260;
        const y = 54 + row * 21;           // same as $rowY in blade

        const fills = Array.from({length: 4}, (_, option) => {
            const x = colStartX + 85 + option * 36;  // same as $bubbleX in blade
            // Local paper brightness compensates for smooth lighting gradients.
            const paper = [gray(x-11,y), gray(x+11,y), gray(x,y-9), gray(x,y+9)].sort((a,b)=>a-b)[2];
            if (paper < 90) throw new Error('Image is too dark. Improve lighting and recapture.');
            let dark = 0, total = 0;
            for (let dx=-4;dx<=4;dx++) for (let dy=-4;dy<=4;dy++) {
                if (dx*dx+dy*dy > 16) continue;
                total++; if (gray(x+dx,y+dy) < paper*.68) dark++;
            }
            return dark/total;
        });
        const ranked = fills.map((fill, option)=>({fill,option})).sort((a,b)=>b.fill-a.fill);
        const certain = ranked[0].fill >= .6 && ranked[1].fill <= .2;
        return {item:index+1, answer:certain ? 'ABCD'[ranked[0].option] : null, fills,
            reason:ranked[0].fill < .25 ? 'Blank — confirm' : certain ? 'Detected' : 'Ambiguous — review'};
    });
}
