(() => {
    const form=document.querySelector('[data-assistant-profile-form]');if(!form)return;
    const name=form.querySelector('[name=name]');const file=form.querySelector('[name=photo]');const wrap=form.querySelector('.assistant-avatar-wrap');let objectUrl=null;
    name.addEventListener('input',()=>{form.querySelector('[data-profile-name-preview]').textContent=name.value.trim()||'Nama asisten';wrap.querySelector('.assistant-avatar-fallback').textContent=(Array.from(name.value.trim())[0]||'?').toLocaleUpperCase('id');});
    file.addEventListener('change',()=>{if(objectUrl){URL.revokeObjectURL(objectUrl);objectUrl=null;}const selected=file.files[0];if(!selected)return;
        if(!['image/jpeg','image/png','image/webp'].includes(selected.type)||selected.size>2*1024*1024)return;
        let image=wrap.querySelector('img');if(!image){image=document.createElement('img');image.className='assistant-avatar';image.alt='';wrap.prepend(image);}
        objectUrl=URL.createObjectURL(selected);image.src=objectUrl;image.hidden=false;wrap.querySelector('.assistant-avatar-fallback').hidden=true;
    });
    window.addEventListener('pagehide',()=>{if(objectUrl)URL.revokeObjectURL(objectUrl);});
})();
