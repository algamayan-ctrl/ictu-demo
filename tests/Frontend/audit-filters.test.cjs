const test=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const fixtures=process.env.AUDIT_FILTER_HTML_DIR;
let JSDOM;
try { ({JSDOM}=require(process.env.AUDIT_TEST_JSDOM||'jsdom')); } catch {}
const enabled=Boolean(fixtures&&JSDOM);

// This is the interaction check using the rendered Audit Trail and its shipped filter scripts.
async function page(t,file='audit.html',prepare){
    const dom=new JSDOM(fs.readFileSync(path.join(fixtures,file),'utf8'),{url:'http://127.0.0.1:8001/reports/audit',runScripts:'outside-only',pretendToBeVisual:true});
    t.after(()=>dom.window.close());
    const w=dom.window,d=w.document,submissions=[];
    await new Promise(resolve=>w.setTimeout(resolve,0));
    const form=d.getElementById('audit-filter-form'),picker=d.querySelector('[data-audit-actor-picker]');
    const input=picker.querySelector('[data-audit-actor-search]'),hidden=d.getElementById('audit-actor-filter');
    const panel=picker.querySelector('[data-audit-actor-panel]');
    w.HTMLFormElement.prototype.submit=function(){submissions.push(new URLSearchParams(new w.FormData(this)));};
    w.fetch=()=>{throw new Error('The Actor picker must reuse its authorized choices without extra requests.');};
    if(prepare)prepare({w,d,picker,input,hidden,panel});
    const inline=[...d.scripts].find(script=>script.textContent.includes('const submitFilters ='));
    assert.ok(inline,'The fixture must contain the actual filter/row-action handlers.');
    w.eval(inline.textContent);
    d.dispatchEvent(new w.Event('DOMContentLoaded'));
    w.eval(fs.readFileSync(path.resolve(__dirname,'../../public/js/audit-filters.js'),'utf8'));
    const query=text=>{input.focus();input.value=text;input.dispatchEvent(new w.Event('input',{bubbles:true}));};
    const key=key=>input.dispatchEvent(new w.KeyboardEvent('keydown',{key,bubbles:true,cancelable:true}));
    let choices=[];
    try { choices=JSON.parse(picker.querySelector('[data-audit-actor-options]').textContent||'[]'); } catch {}
    return {w,d,form,picker,input,hidden,panel,submissions,query,key,
        options:()=>[...panel.querySelectorAll('[role=option]')],
        choices};
}

test('Actor matches are bounded and typing does not apply an event filter',{skip:!enabled},async t=>{
    const p=await page(t);
    assert.equal(p.hidden.value,'ALL');
    assert.equal(p.input.value,'All actors');
    p.input.focus();
    assert.equal(p.options().length,2,'Empty search shows only All actors and the available System shortcut.');
    p.query('Search Actor');
    assert.equal(p.options().length,9);
    assert.match(p.panel.textContent,/12 matching actors/);
    assert.match(p.panel.textContent,/first 8 names/);
    assert.equal(p.input.getAttribute('aria-expanded'),'true');
    assert.equal(p.hidden.value,'ALL');
    assert.equal(p.submissions.length,0);
});

test('Keyboard selection applies the actor ID once and resets pagination',{skip:!enabled},async t=>{
    const p=await page(t);
    p.query('SPMU Head');
    assert.equal(p.options().length,2);
    p.key('ArrowDown');
    assert.equal(p.d.getElementById(p.input.getAttribute('aria-activedescendant')).textContent,'SPMU Head Demo');
    p.key('Enter');
    const selected=p.choices.find(option=>option.label==='SPMU Head Demo');
    assert.equal(p.hidden.value,selected.value);
    assert.equal(p.input.value,selected.label);
    assert.equal(p.submissions.length,1);
    assert.equal(p.submissions[0].get('actor'),selected.value);
    assert.equal(p.submissions[0].get('per_page'),'28');
    assert.equal(p.submissions[0].has('page'),false);
    assert.equal(p.panel.hidden,true);
    assert.equal(p.input.getAttribute('aria-expanded'),'false');
    p.key('Enter');
    assert.equal(p.submissions.length,1);
});

test('Clearing Actor preserves other filter values and submits only an explicit reset',{skip:!enabled},async t=>{
    const p=await page(t,'audit-selected.html');
    assert.notEqual(p.hidden.value,'ALL');
    p.picker.querySelector('[data-audit-actor-clear]').click();
    assert.equal(p.hidden.value,'ALL');
    assert.equal(p.input.value,'All actors');
    assert.equal(p.submissions.length,1);
    assert.equal(p.submissions[0].get('category'),'ADMINISTRATION_SETTINGS');
    assert.equal(p.submissions[0].get('date'),'TODAY');
    assert.equal(p.submissions[0].get('q'),'Recorded');
    assert.equal(p.submissions[0].get('per_page'),'28');
    assert.equal(p.picker.querySelector('[data-audit-actor-clear]').hidden,true);
});

test('No results, Escape, Tab and outside clicks preserve the applied actor',{skip:!enabled},async t=>{
    const p=await page(t,'audit-selected.html');
    const applied=p.hidden.value;
    p.query('No Such Actor');
    assert.match(p.panel.textContent,/No matching actors/);
    p.key('Enter');
    assert.equal(p.submissions.length,0);
    assert.equal(p.hidden.value,applied);
    p.key('Escape');
    assert.equal(p.panel.hidden,true);
    assert.equal(p.input.value,'SPMU Head Demo');
    p.query('Uncommitted');
    assert.equal(p.input.value,'Uncommitted');
    p.key('Tab');
    assert.equal(p.input.value,'SPMU Head Demo');
    p.input.click();p.query('Still uncommitted');
    p.d.getElementById('audit-category-filter').dispatchEvent(new p.w.Event('pointerdown',{bubbles:true}));
    assert.equal(p.panel.hidden,true);
    assert.equal(p.hidden.value,applied);
    assert.equal(p.submissions.length,0);
});

test('System and All actors remain explicit selectable choices',{skip:!enabled},async t=>{
    const p=await page(t);
    p.query('system');p.key('ArrowDown');p.key('Enter');
    assert.equal(p.hidden.value,'SYSTEM');
    assert.equal(p.submissions[0].get('actor'),'SYSTEM');
    const absent=await page(t,'audit.html',({picker})=>{picker.dataset.systemActor='0';});
    absent.query('system');
    assert.deepEqual(absent.options().map(option=>option.dataset.actorValue),['ALL']);
    assert.match(absent.panel.textContent,/No matching actors/);
});

test('Duplicate names keep their own IDs and show distinguishing account labels',{skip:!enabled},async t=>{
    const p=await page(t);
    p.query('Same Recorded Name');
    const matches=p.options().filter(option=>option.dataset.actorValue!=='ALL');
    assert.equal(matches.length,2);
    assert.match(matches[0].textContent,/one@cspc.edu.ph/);
    assert.match(matches[1].textContent,/two@cspc.edu.ph/);
    matches[1].click();
    assert.equal(p.hidden.value,matches[1].dataset.actorValue);
    assert.equal(p.submissions.length,1);
    assert.equal(p.submissions[0].get('actor'),matches[1].dataset.actorValue);
});

test('The matching panel stays inside narrow viewports and can open above the input',{skip:!enabled},async t=>{
    const p=await page(t);
    Object.defineProperty(p.w,'innerWidth',{value:390,configurable:true});
    Object.defineProperty(p.w,'innerHeight',{value:700,configurable:true});
    p.input.getBoundingClientRect=()=>({left:220,right:382,top:630,bottom:668,width:162,height:38});
    p.panel.getBoundingClientRect=()=>({height:280,width:280});
    p.query('SPMU');
    const left=parseFloat(p.panel.style.left),width=parseFloat(p.panel.style.width),top=parseFloat(p.panel.style.top);
    assert.ok(left>=8&&left+width<=382);
    assert.ok(top>=8&&top+280<=692);
    assert.ok(top<630);
    assert.equal(p.panel.parentElement,p.d.body);
    p.key('Escape');
    assert.equal(p.panel.parentElement,p.picker);
});

test('Unavailable actor options do not silently replace an applied filter',{skip:!enabled},async t=>{
    const p=await page(t,'audit-selected.html',({picker})=>{picker.querySelector('[data-audit-actor-options]').textContent='{invalid';});
    assert.equal(p.input.disabled,true);
    assert.equal(p.picker.querySelector('[data-audit-actor-error]').hidden,false);
    assert.notEqual(p.hidden.value,'ALL');
    assert.equal(p.submissions.length,0);
});

test('Actor names are rendered as text rather than HTML',{skip:!enabled},async t=>{
    const p=await page(t,'audit.html',({picker})=>{
        picker.querySelector('[data-audit-actor-options]').textContent=JSON.stringify([{value:'12345',label:'Actor <img src=x onerror=alert(1)>',detail:''}]);
    });
    p.query('Actor');
    assert.equal(p.panel.querySelector('img'),null);
    assert.match(p.options()[1].textContent,/<img src=x/);
});

test('Changing Category retains the applied actor despite an uncommitted Actor search',{skip:!enabled},async t=>{
    const p=await page(t,'audit-selected.html');
    const applied=p.hidden.value;
    p.query('Uncommitted name');
    const category=p.d.getElementById('audit-category-filter');category.value='INVENTORY';
    category.dispatchEvent(new p.w.Event('change',{bubbles:true}));
    assert.equal(p.submissions.length,1);
    assert.equal(p.submissions[0].get('actor'),applied);
    assert.equal(p.submissions[0].get('category'),'INVENTORY');
});

test('Existing row disclosures remain usable and only one stays open',{skip:!enabled},async t=>{
    const p=await page(t);
    const toggles=[...p.d.querySelectorAll('[data-audit-details-toggle]')];
    assert.ok(toggles.length>=2);
    toggles[0].click();assert.equal(toggles[0].getAttribute('aria-expanded'),'true');
    toggles[1].click();assert.equal(toggles[0].getAttribute('aria-expanded'),'false');
    assert.equal(toggles[1].getAttribute('aria-expanded'),'true');
});
