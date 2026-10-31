(() => {
    'use strict';
    document.querySelectorAll('[data-assistant-avatar]').forEach(image=>{
        const fallback=()=>{image.hidden=true;const initial=image.parentElement.querySelector('.assistant-avatar-fallback');if(initial)initial.hidden=false;};
        image.addEventListener('error',fallback);if(image.complete&&!image.naturalWidth)fallback();
    });
    const widget=document.querySelector('[data-system-assistant]');
    if(!widget) return;
    const assistantName=widget.dataset.name||'Sukoco';
    const panel=widget.querySelector('.assistant-panel');
    const launcher=widget.querySelector('[data-assistant-open]');
    const messages=widget.querySelector('[data-assistant-messages]');
    const initial=messages.firstElementChild.cloneNode(true);
    const status=widget.querySelector('[data-assistant-status]');
    const question=widget.querySelector('#assistant-question');
    const search=widget.querySelector('[data-assistant-search]');
    const mobile=window.matchMedia('(max-width:700px)');
    const shell=document.querySelector('.app-shell');
    let request=null;
    let oldInert=false;
    let modal=false;
    function syncModal(){
        const active=!panel.hidden && mobile.matches;
        if(active && !modal){oldInert=shell?.inert??false;if(shell) shell.inert=true;}
        if(!active && modal && shell) shell.inert=oldInert;
        modal=active;widget.querySelector('[data-assistant-overlay]').hidden=!active;
        document.documentElement.classList.toggle('assistant-open',active);
        if(active) panel.setAttribute('aria-modal','true');else panel.removeAttribute('aria-modal');
        if(active && !panel.contains(document.activeElement)) requestAnimationFrame(()=>question.focus());
    }
    function open(){
        if(document.getElementById('sidebar')?.classList.contains('open')) window.toggleSidebar?.();
        panel.hidden=false;launcher.setAttribute('aria-expanded','true');syncModal();
        requestAnimationFrame(()=>question.focus());
    }
    function close(){panel.hidden=true;launcher.setAttribute('aria-expanded','false');syncModal();launcher.focus();}
    launcher.addEventListener('click',()=>panel.hidden?open():close());
    widget.querySelector('[data-assistant-close]').addEventListener('click',close);
    widget.querySelector('[data-assistant-overlay]').addEventListener('click',close);
    mobile.addEventListener('change',syncModal);
    panel.addEventListener('keydown',event=>{
        if(event.key==='Escape'){event.preventDefault();event.stopPropagation();close();return;}
        if(event.key!=='Tab' || !modal) return;
        const items=[...panel.querySelectorAll('a[href],button:not(:disabled),input,select,textarea,summary')].filter(x=>x.getClientRects().length && (typeof x.checkVisibility!=='function'||x.checkVisibility()));
        if(event.shiftKey && document.activeElement===items[0]){event.preventDefault();items.at(-1)?.focus();}
        else if(!event.shiftKey && document.activeElement===items.at(-1)){event.preventDefault();items[0]?.focus();}
    });
    const element=(tag,text)=>{const node=document.createElement(tag);node.textContent=text;return node;};
    function bubble(text,user=false){
        const box=element('div','');box.className='assistant-message '+(user?'assistant-user':'assistant-reply');
        box.append(element('strong',user?'Anda':assistantName),element('p',text));messages.append(box);
        while(messages.children.length>30) messages.firstElementChild.remove();
        return box;
    }
    function link(label,url){
        try{const parsed=new URL(url,location.href);if(parsed.origin!==location.origin || !['http:','https:'].includes(parsed.protocol)) return null;
            const a=element('a',label);a.href=parsed.href;return a;
        }catch{return null;}
    }
    function render(data){
        const box=bubble(data.answer||'Tidak ada jawaban. Coba pertanyaan yang lebih spesifik.');
        if(data.steps?.length){const list=element('ol','');data.steps.forEach(step=>list.append(element('li',step)));box.append(list);}
        (data.charts||[]).forEach(chart=>{
            const figure=element('figure','');figure.className='assistant-chart';figure.append(element('figcaption',chart.title));
            const items=(chart.items||[]).slice(0,14);const maximum=Math.max(0,...items.map(item=>Math.abs(Number(item.value)||0)));
            items.forEach(item=>{const value=Number(item.value);const width=Number.isFinite(value)&&maximum>0?Math.min(100,100*Math.abs(value)/maximum):0;
                const row=element('div','');row.className='assistant-chart-row';const label=element('p',`${item.label}: ${item.formatted}`);row.append(label);
                const track=element('div','');track.className='assistant-chart-track';track.setAttribute('aria-hidden','true');const bar=element('span','');bar.style.width=width+'%';if(value<0)bar.classList.add('negative');track.append(bar);row.append(track);figure.append(row);
            });
            if(chart.basis) figure.append(element('p',chart.basis));
            if(items.some(item=>Number(item.value)<0))figure.append(element('p','Batang memakai besar nilai absolut; nilai negatif ditulis dengan tanda minus.'));
            box.append(figure);
        });
        (data.results||[]).forEach(result=>{const item=element('div','');item.className='assistant-result';item.append(link(result.title,result.url)||element('strong',result.title),element('p',result.detail||''));box.append(item);});
        (data.links||[]).forEach(item=>{const a=link(item.label,item.url);if(a) box.append(a);});
        if(data.notice){const note=element('p',data.notice);note.className='assistant-notice';box.append(note);}
        messages.scrollTop=messages.scrollHeight;
    }
    function busy(active){widget.querySelectorAll('form button[type=submit],[data-assistant-question]').forEach(button=>button.disabled=active);messages.setAttribute('aria-busy',String(active));}
    async function ask(payload,label){
        if(request) return;
        let success=false;
        bubble(label,true);messages.scrollTop=messages.scrollHeight;
        const controller=new AbortController();request=controller;busy(true);status.textContent='Sedang memeriksa panduan / data…';
        try{
            const response=await fetch(widget.dataset.endpoint,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content},body:JSON.stringify({...payload,context:widget.dataset.context}),signal:controller.signal});
            const data=await response.json().catch(()=>({}));
            if(!response.ok){
                const text=response.status===419||response.status===401?'Sesi berakhir. Masuk kembali untuk menggunakan asisten.':response.status===403?'Data/topik ini tidak tersedia untuk hak akses akun Anda.':response.status===429?'Terlalu banyak permintaan. Tunggu satu menit lalu coba kembali.':response.status===422?Object.values(data.errors||{}).flat()[0]||'Periksa kata pencarian dan tanggal.':'Asisten belum dapat mengambil data. Coba kembali atau buka halaman modul.';
                render({answer:text});
            }else{render(data);success=true;}
            status.textContent='Selesai. Hasil hanya mengikuti data dan hak akses saat ini.';
        }catch(error){if(error.name!=='AbortError'){render({answer:'Koneksi bermasalah. Coba kembali; tidak ada data yang diubah.'});status.textContent='Permintaan belum berhasil.';}}
        finally{if(request===controller){request=null;busy(false);}}
        return success;
    }
    widget.querySelector('[data-assistant-chat]').addEventListener('submit',event=>{event.preventDefault();const text=question.value.trim();if(!text||request) return;question.value='';ask({mode:'auto',message:text},text);});
    question.addEventListener('keydown',event=>{if(event.key==='Enter'&&!event.shiftKey&&!event.isComposing){event.preventDefault();widget.querySelector('[data-assistant-chat]').requestSubmit();}});
    widget.querySelectorAll('[data-assistant-question]').forEach(button=>button.addEventListener('click',()=>ask({mode:'auto',message:button.dataset.assistantQuestion},button.dataset.assistantQuestion)));
    widget.querySelector('[data-assistant-clear]').addEventListener('click',()=>{request?.abort();request=null;busy(false);messages.replaceChildren(initial.cloneNode(true));question.value='';if(search){search.reset();search.querySelector('[name=resource]').dispatchEvent(new Event('change'));}status.textContent='Percakapan dibersihkan. Riwayat tidak disimpan di browser.';question.focus();});
    if(search){
        const resource=search.querySelector('[name=resource]');
        const updateDates=()=>{const enabled=['receipts','expenses','claims'].includes(resource.value);search.querySelector('[data-assistant-dates]').hidden=!enabled;search.querySelector('[data-assistant-date-note]').hidden=!enabled;};
        resource.addEventListener('change',updateDates);updateDates();
        search.addEventListener('submit',async event=>{event.preventDefault();if(request) return;
            const payload={mode:'search',resource:resource.value,term:search.querySelector('[name=term]').value.trim()};
            if(['receipts','expenses','claims'].includes(resource.value)) ['from','to'].forEach(key=>{const value=search.querySelector(`[name=${key}]`).value;if(value) payload[key]=value;});
            if(await ask(payload,`Cari ${resource.selectedOptions[0].textContent}: ${payload.term||'data terbaru'}${payload.from?' dari '+payload.from:''}${payload.to?' sampai '+payload.to:''}`)){search.closest('details').open=false;question.focus();}
        });
    }
    const analysis=widget.querySelector('[data-assistant-analysis]');
    if(analysis){
        const type=analysis.querySelector('[name=resource]');
        const dates=()=>{analysis.querySelector('[data-analysis-dates]').hidden=type.value==='assets';};
        type.addEventListener('change',dates);dates();
        analysis.addEventListener('submit',async event=>{event.preventDefault();if(request) return;const payload={mode:'analysis',resource:type.value};
            if(type.value!=='assets') ['from','to'].forEach(key=>{const value=analysis.querySelector(`[name=${key}]`).value;if(value)payload[key]=value;});
            if(await ask(payload,`Analisis ${type.selectedOptions[0].textContent}${payload.from?' dari '+payload.from:''}${payload.to?' sampai '+payload.to:''}`)){analysis.closest('details').open=false;question.focus();}
        });
        widget.querySelector('[data-analysis-context]').addEventListener('click',async()=>{
            const params=new URLSearchParams(location.search);const payload={mode:'analysis',message:'baca laporan halaman ini'};
            const from=params.get('from')||params.get('date_from');const to=params.get('to')||params.get('date_to')||params.get('as_of');
            if(from)payload.from=from;if(to)payload.to=to;
            const context=widget.dataset.context;let year=params.get('year')||params.get('tahun');let month=params.get('month');
            if(!year && ['dashboard','reports.yearly','reports.monthly'].includes(context))year=widget.dataset.today.slice(0,4);
            if(!month && context==='reports.monthly')month=widget.dataset.today.slice(5,7);
            if(!from&&!to&&/^20\d{2}$/.test(year||'')){
                if(month&&/^\d{1,2}$/.test(month)&&Number(month)>=1&&Number(month)<=12){const m=Number(month);payload.from=`${year}-${String(m).padStart(2,'0')}-01`;payload.to=new Date(Date.UTC(Number(year),m,0)).toISOString().slice(0,10);}
                else{payload.from=year+'-01-01';payload.to=year+'-12-31';}
                if(payload.to>widget.dataset.today)payload.to=widget.dataset.today;
            }
            if(await ask(payload,'Baca laporan/grafik halaman ini dengan periode dari filter halaman (jika tersedia).')){analysis.closest('details').open=false;question.focus();}
        });
    }
})();
