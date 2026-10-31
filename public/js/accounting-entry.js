(() => {
    const form=document.querySelector('[data-accounting-entry]');
    if(!form) return;
    const preview=form.querySelector('[data-entry-preview]');
    function refresh(){
        const amount=form.querySelector('input[name=amount]')?.value || '';
        const debit=form.querySelector('[name=debit_account_id]');
        const credit=form.querySelector('[name=credit_account_id]');
        if(!debit.value || !credit.value || !/^\d+(\.\d{1,2})?$/.test(amount)){
            preview.textContent='Isi akun dan nominal untuk melihat pasangan jurnal.'; return;
        }
        const [whole,fraction='']=amount.split('.');
        const money='Rp '+whole.replace(/\B(?=(\d{3})+(?!\d))/g,'.')+','+fraction.padEnd(2,'0');
        preview.textContent=`Debit ${debit.selectedOptions[0].textContent.trim()}: ${money}. Kredit ${credit.selectedOptions[0].textContent.trim()}: ${money}.`;
    }
    form.addEventListener('input',refresh);form.addEventListener('change',refresh);refresh();
})();
