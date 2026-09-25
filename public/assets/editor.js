'use strict';
(()=>{
 const field=document.querySelector('#article-content'),form=document.querySelector('#article-form'),error=document.querySelector('#editor-error');
 if(!field||!form)return;
 let dirty=form.dataset.unsaved==='true',submitting=false;
 form.addEventListener('input',()=>dirty=true);form.addEventListener('change',()=>dirty=true);
 window.addEventListener('beforeunload',event=>{if(dirty&&!submitting){event.preventDefault();event.returnValue='';}});
 const showError=message=>{error.hidden=false;error.textContent=message;error.scrollIntoView({block:'nearest'});};
 if(!window.tinymce){showError('The visual editor could not load. Your HTML is available in the text area. Contact your administrator.');return;}
 tinymce.init({
  selector:'#article-content',license_key:'gpl',height:480,
  plugins:'lists link image table code codesample searchreplace',
  menubar:'edit insert format table tools',
  toolbar:'undo redo | blocks | bold italic | bullist numlist | link image table | codesample | code',
  block_formats:'Paragraph=p; Heading 2=h2; Heading 3=h3; Heading 4=h4; Preformatted=pre',
  branding:true,promotion:false,content_css:field.dataset.contentCss,
  relative_urls:false,remove_script_host:true,convert_urls:false,
  paste_data_images:true,automatic_uploads:true,images_file_types:'jpg,jpeg,png,webp',
  file_picker_types:'image',
  file_picker_callback:callback=>{
   const input=document.createElement('input');input.type='file';input.accept='image/png,image/jpeg,image/webp';
   input.addEventListener('change',()=>{
    const file=input.files[0];if(!file)return;
    if(file.size>10*1024*1024){showError('Choose an image smaller than 10 MB.');return;}
    const reader=new FileReader();reader.onload=()=>{const cache=tinymce.activeEditor.editorUpload.blobCache;const info=cache.create('image-'+Date.now(),file,String(reader.result).split(',')[1]);cache.add(info);callback(info.blobUri(),{alt:file.name});};reader.readAsDataURL(file);
   });input.click();
  },
  images_upload_handler:async(blobInfo)=>{
   const data=new FormData();data.append('file',blobInfo.blob(),blobInfo.filename());data.append('csrf',form.elements.csrf.value);
   const response=await fetch(field.dataset.uploadUrl,{method:'POST',body:data,credentials:'same-origin'});
   let result;try{result=await response.json();}catch{throw new Error('Image upload failed. Check the server upload size limits.');}
   if(!response.ok||!result.location)throw new Error(result.error||'Image upload failed.');
   dirty=true;return result.location;
  },
  setup:editor=>{editor.on('change input undo redo',()=>dirty=true);editor.on('init',()=>{editor.save();});}
 });
 form.addEventListener('submit',async event=>{
  if(submitting)return;
  event.preventDefault();error.hidden=true;
  const action=event.submitter?.value||'draft';
  const buttons=[...form.querySelectorAll('button[type="submit"]')];buttons.forEach(b=>b.disabled=true);
  try{
   const editor=tinymce.get('article-content');if(!editor?.initialized)throw new Error('The editor is still loading. Try again in a moment.');
   const uploaded=await editor.uploadImages();
   if(uploaded.some(item=>item.status===false))throw new Error('An image upload failed. Remove it or upload it again before saving.');
   editor.save();
   if(/src=["'](?:blob:|data:)/i.test(field.value))throw new Error('An image is still uploading. Please try saving again.');
   const actionField=document.createElement('input');actionField.type='hidden';actionField.name='action';actionField.value=action;form.append(actionField);
   submitting=true;dirty=false;HTMLFormElement.prototype.submit.call(form);
  }catch(e){showError(e.message||String(e));buttons.forEach(b=>b.disabled=false);}
 });
})();
