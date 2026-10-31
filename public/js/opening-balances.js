(() => {
    const form=document.querySelector('[data-opening-form]');
    if(!form) return;
    const rows=form.querySelector('[data-opening-rows]');
    const total=form.querySelector('[data-opening-total]');
    const money=value=>{
        const sign=value<0n?'-':''; const amount=value<0n?-value:value;
        return 'Rp '+sign+(amount/100n).toString().replace(/\B(?=(\d{3})+(?!\d))/g,'.')+','+(amount%100n).toString().padStart(2,'0');
    };
    function refresh(){
        let debit=0n,credit=0n,invalid=false;
        rows.querySelectorAll('[data-opening-row]').forEach(row=>{
            const account=row.querySelector('select[name$="[chart_account_id]"]');
            const amount=row.querySelector('input[name$="[amount]"]')?.value || '0';
            const side=row.querySelector('select[name$="[side]"]').value;
            if(!/^\d+(?:\.\d{0,2})?$/.test(amount)){invalid=true;return;}
            const [whole,fraction='']=amount.split('.');
            const cents=BigInt(whole)*100n+BigInt(fraction.padEnd(2,'0'));
            if(cents && !account.value){invalid=true;return;}
            const selected=side==='NORMAL'?account.selectedOptions[0]?.dataset.balance:side;
            if(selected==='DEBIT') debit+=cents;
            else if(selected==='CREDIT') credit+=cents;
        });
        total.textContent=invalid?'Lengkapi akun dan nominal yang valid pada setiap baris.':`Total debit ${money(debit)} · Total kredit ${money(credit)} · ${debit===credit?'Seimbang secara hitungan':'Selisih '+money(debit-credit)}`;
    }
    form.querySelector('[data-add-opening-row]').addEventListener('click',()=>{
        const entries=[...rows.querySelectorAll('[data-opening-row]')];
        if(entries.length>=500) return;
        const index=Math.max(-1,...entries.map(row=>Number(row.dataset.index)))+1;
        const template=document.createElement('template');
        template.innerHTML=form.querySelector('[data-opening-template]').innerHTML.replaceAll('__INDEX__',String(index));
        rows.append(template.content.cloneNode(true)); refresh();
    });
    rows.addEventListener('click',event=>{const button=event.target.closest('[data-remove-opening-row]');if(button && rows.querySelectorAll('[data-opening-row]').length>2){button.closest('[data-opening-row]').remove();refresh();}});
    form.addEventListener('input',refresh);form.addEventListener('change',refresh);refresh();
})();
