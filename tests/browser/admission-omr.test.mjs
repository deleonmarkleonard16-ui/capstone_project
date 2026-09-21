import test from 'node:test';
import assert from 'node:assert/strict';
import {detectAnswers, projectiveMap} from '../../public/js/admission-omr.js';

const corners = [[20,160],[1080,160],[1080,620],[20,620]];
function sheet() { return {width:1100,height:650,data:new Uint8ClampedArray(1100*650*4).fill(255)}; }
function bubble(image,item,option) {
    const x=100+Math.floor((item-1)/20)*270+option*36, y=190+(item-1)%20*21;
    for(let dx=-6;dx<=6;dx++)for(let dy=-6;dy<=6;dy++)if(dx*dx+dy*dy<=36){
        const offset=((y+dy)*image.width+x+dx)*4;
        image.data[offset]=image.data[offset+1]=image.data[offset+2]=0;
    }
}
test('all 80 filled bubbles are read in column order',()=>{
    const image=sheet();for(let i=1;i<=80;i++)bubble(image,i,(i-1)%4);
    const answers=detectAnswers(image,corners);
    assert.equal(answers.length,80);
    answers.forEach((row,i)=>assert.equal(row.answer,'ABCD'[i%4]));
});
test('blank and multiple marks require review',()=>{
    const image=sheet();bubble(image,2,0);bubble(image,2,1);
    const answers=detectAnswers(image,corners);
    assert.equal(answers[0].answer,null);assert.match(answers[0].reason,/Blank/);
    assert.equal(answers[1].answer,null);assert.match(answers[1].reason,/Ambiguous/);
});
test('perspective transform maps all four marker centers',()=>{
    const target=[[60,30],[1200,80],[1000,640],[30,590]], map=projectiveMap(target);
    corners.forEach((point,i)=>map(...point).forEach((value,j)=>assert.ok(Math.abs(value-target[i][j])<1e-5)));
});
test('crossed markers and dark photographs are rejected',()=>{
    assert.throws(()=>detectAnswers(sheet(),[corners[0],corners[2],corners[1],corners[3]]),/clockwise/);
    const dark=sheet();dark.data.fill(0);assert.throws(()=>detectAnswers(dark,corners),/dark/);
});
