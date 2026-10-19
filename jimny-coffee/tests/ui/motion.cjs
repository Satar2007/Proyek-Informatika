const fs = require('fs');
const path = require('path');
const vm = require('vm');
const assert = require('assert/strict');
const source = fs.readFileSync(path.join(__dirname,'../../public/js/pos-motion.js'),'utf8');
const nodes = new Set();
const preference = {matches:false,addEventListener(){}};
class FakeElement {
    constructor(){this.style={};this.animations=[];}
    setAttribute(){}
    remove(){nodes.delete(this);}
    getAnimations(){return this.animations;}
    animate(){let finish;const finished=new Promise(r=>{finish=r});const a={finished,cancel:()=>finish(),finish};this.animations.push(a);return a;}
}
const context = {Element:FakeElement,innerWidth:1920,innerHeight:980,
 window:{matchMedia:()=>preference},
 document:{readyState:'loading',addEventListener(){},createElement:()=>new FakeElement(),body:{append:el=>nodes.add(el)}}};
vm.createContext(context);vm.runInContext(source,context);
const motion=context.window.PosMotion;
const card={getBoundingClientRect:()=>({right:250,bottom:350})};
const cart={getBoundingClientRect:()=>({top:150,bottom:900,right:1900}),querySelector:()=>null};
motion.flyToCart(null,cart);assert.equal(nodes.size,0);
preference.matches=true;motion.flyToCart(card,cart);assert.equal(nodes.size,0);
preference.matches=false;
for(let i=0;i<30;i++)motion.flyToCart(card,cart);
assert.ok(nodes.size<=8,'Rapid clicks must not accumulate particles');
for(const node of nodes)node.getAnimations().forEach(a=>a.finish());
setImmediate(()=>{assert.equal(nodes.size,0,'Finished animations must remove particles');console.log('PASS: reduced motion skips effects; rapid clicks capped; completed effects cleaned up.');});
