// OMR v2 — Dynamic item count support.
// Coordinates are derived from the SVG layout in admin/admission/paper.blade.php.
// The projective transform maps webcam/image pixel coords → SVG viewBox coords.
// SVG layout constants (must stay in sync with paper.blade.php):
//   colStartX  = 40 + col * 260
//   bubbleX    = colStartX + 85 + optionIndex * 36
//   rowY (first item in col) = 54, step = 21
//   4 corner markers at: TL(20,160), TR(1080,160), BR(1080,620), BL(20,620)
//   (these are the approximate midpoints of the markers in scan space)

export function projectiveMap(corners, totalItems = 80) {
    const rowsPerCol = Math.ceil(totalItems / 4);
    const svgHeight = Math.max(54 + rowsPerCol * 21 + 16, 480);
    const source = [[17, 17], [1063, 17], [1063, svgHeight - 17], [17, svgHeight - 17]];
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

/** Find the four isolated, solid black square alignment markers on a photo. */
export function detectCornerMarkers(image) {
    if (!image?.data || !image.width || !image.height) throw new Error('Capture a clear answer-sheet photo first.');

    const scale = Math.max(1, Math.ceil(Math.max(image.width, image.height) / 1600));
    const width = Math.ceil(image.width / scale);
    const height = Math.ceil(image.height / scale);
    const size = width * height;
    const dark = new Uint8Array(size);
    for (let y = 0; y < height; y++) {
        const sourceY = Math.min(image.height - 1, y * scale);
        for (let x = 0; x < width; x++) {
            const sourceX = Math.min(image.width - 1, x * scale);
            const offset = (sourceY * image.width + sourceX) * 4;
            const gray = .299 * image.data[offset] + .587 * image.data[offset + 1] + .114 * image.data[offset + 2];
            if (gray < 75) dark[y * width + x] = 1;
        }
    }

    const queue = new Int32Array(size);
    const candidates = [];
    for (let start = 0; start < size; start++) {
        if (!dark[start]) continue;
        let head = 0, tail = 0;
        queue[tail++] = start;
        dark[start] = 0;
        let minX = width, minY = height, maxX = -1, maxY = -1;
        while (head < tail) {
            const index = queue[head++];
            const x = index % width, y = Math.floor(index / width);
            minX = Math.min(minX, x); minY = Math.min(minY, y);
            maxX = Math.max(maxX, x); maxY = Math.max(maxY, y);
            const neighbors = [index - 1, index + 1, index - width, index + width];
            for (const next of neighbors) {
                if (next < 0 || next >= size || !dark[next]) continue;
                // Prevent horizontal wrap between the last and first pixel of adjacent rows.
                if ((next === index - 1 && x === 0) || (next === index + 1 && x === width - 1)) continue;
                dark[next] = 0;
                queue[tail++] = next;
            }
            if (tail > 12000) break;
        }

        const boxWidth = maxX - minX + 1;
        const boxHeight = maxY - minY + 1;
        const area = boxWidth * boxHeight;
        const ratio = boxWidth / boxHeight;
        if (tail < 70 || boxWidth < 8 || boxHeight < 8 || boxWidth > width * .08 || boxHeight > height * .08
            || ratio < .68 || ratio > 1.47 || tail / area < .66) continue;
        candidates.push({
            x: ((minX + maxX) / 2) * scale,
            y: ((minY + maxY) / 2) * scale,
            area: tail * scale * scale,
        });
    }

    function bestPair(side) {
        const sideCandidates = candidates.filter(point => side === 'left'
            ? point.x < image.width * .36
            : point.x > image.width * .64);
        let best = null;
        for (const top of sideCandidates) {
            if (top.y > image.height * .78) continue;
            for (const bottom of sideCandidates) {
                const verticalGap = bottom.y - top.y;
                const horizontalDrift = Math.abs(bottom.x - top.x);
                if (verticalGap < image.height * .2 || horizontalDrift > image.width * .14 || bottom.y < image.height * .42) continue;
                const score = Math.min(top.area, bottom.area) * 5 + verticalGap - horizontalDrift * 2;
                if (!best || score > best.score) best = {top, bottom, score};
            }
        }
        return best;
    }

    const left = bestPair('left');
    const right = bestPair('right');
    if (!left || !right
        || Math.abs(left.top.y - right.top.y) > image.height * .18
        || Math.abs(left.bottom.y - right.bottom.y) > image.height * .18) {
        throw new Error('Could not find all four black corner markers. Keep the complete grid in frame, improve lighting, or align the four markers manually.');
    }

    return [left.top, right.top, right.bottom, left.bottom].map(point => [point.x, point.y]);
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
    const project = projectiveMap(corners, totalItems);
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
            // Keep the paper reference fixed at the nominal bubble location so
            // alignment-search offsets do not turn the printed circle outline
            // into a false shaded answer.
            const paper = [gray(x-12,y), gray(x+12,y), gray(x,y-12), gray(x,y+12)].sort((a,b)=>a-b)[2];
            if (paper < 90) throw new Error('Image is too dark. Improve lighting and recapture.');
            const measureAt = (centerX, centerY) => {
                // Read most of the bubble interior, not just a tiny center patch.
                let dark = 0, total = 0;
                for (let dx=-2;dx<=2;dx++) for (let dy=-2;dy<=2;dy++) {
                    if (dx*dx+dy*dy > 4) continue;
                    const value = gray(centerX+dx,centerY+dy);
                    total++;
                    if (value < paper * .82) dark++;
                }
                return dark / total;
            };

            // Marker centers can be a few pixels off after a phone photo. Search a
            // small area for the strongest bubble fill instead of sampling one point.
            let strongest = 0;
            const offsets = [[0,0],[-3,0],[3,0],[0,-3],[0,3],[-2,-2],[-2,2],[2,-2],[2,2]];
            for (const [offsetX, offsetY] of offsets) {
                strongest = Math.max(strongest, measureAt(x + offsetX, y + offsetY));
            }
            return strongest;
        });
        const ranked = fills.map((fill, option)=>({fill,option})).sort((a,b)=>b.fill-a.fill);
        // A nearby circle outline can raise every option's baseline slightly;
        // accept a clear winner while still requiring review for ties.
        const certain = ranked[0].fill >= .5 && ranked[0].fill - ranked[1].fill >= .25;
        return {item:index+1, answer:certain ? 'ABCD'[ranked[0].option] : null, fills,
            reason:ranked[0].fill <= .4 ? 'Blank — confirm' : certain ? 'Detected' : 'Ambiguous — review'};
    });
}
