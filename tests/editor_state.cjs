'use strict';
// Exercise the real editor event handlers without requiring a browser runtime.
// This covers state transitions only; browser integration has a separate smoke test.
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const path=require('node:path');
const script=fs.readFileSync(path.join(__dirname,'../public/assets/editor.js'),'utf8');
function page(unsaved){
 const events={window:{},form:{},editor:{}};
 const listen=target=>(names,handler)=>{for(const name of names.split(' '))events[target][name]=handler;};
 const form={dataset:{unsaved:String(unsaved)},addEventListener:listen('form')};
 const field={dataset:{}};
 const editor={on:listen('editor'),save(){}};
 const tinymce={init(options){options.setup(editor);}};
 const window={tinymce,addEventListener:listen('window')};
 const document={querySelector(selector){return selector==='#article-form'?form:selector==='#article-content'?field:{};}};
 vm.runInNewContext(script,{window,document,tinymce});
 return {
  initialize(){events.editor.init();},
  type(){events.form.input();},
  warns(){let prevented=false;events.window.beforeunload({preventDefault(){prevented=true;}});return prevented;}
 };
}
const saved=page(false);
saved.initialize();
assert.equal(saved.warns(),false,'a clean or successfully saved page must not warn');
const recovered=page(true);
assert.equal(recovered.warns(),true,'failed-save text must be protected before editor initialization');
recovered.initialize();
assert.equal(recovered.warns(),true,'failed-save text must remain protected after initialization');
const earlyEdit=page(false);
earlyEdit.type();earlyEdit.initialize();
assert.equal(earlyEdit.warns(),true,'typing while the editor loads must remain protected');
saved.type();
assert.equal(saved.warns(),true,'fresh edits must trigger the warning');
console.log('PASS editor state: saved, failed-save recovery, early edits, and later edits');
