(function(){
  const BASE_PATH = (window.SHOP_BASE_PATH || '').replace(/\/+$/,'');
  const API_CART = '/plugins/shop/api/cart.php';
  const API_META = '/plugins/shop/api/meta.php';
  const API_CHECKOUT = '/plugins/shop/api/checkout.php';

  function q(sel,root){return (root||document).querySelector(sel);} 
  function qa(sel,root){return Array.from((root||document).querySelectorAll(sel));}

  async function post(url,data){
    const body = new URLSearchParams();
    Object.keys(data||{}).forEach(k=>body.append(k,data[k]));
    const res = await fetch(url,{
      method:'POST',
      credentials:'same-origin',
      headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},
      body
    });
    const json = await res.json().catch(()=>null);
    if(!res.ok){throw Object.assign(new Error('Request failed'),{status:res.status,json});}
    return json;
  }

  async function get(url){
    const res = await fetch(url,{credentials:'same-origin'});
    const json = await res.json().catch(()=>null);
    if(!res.ok){throw Object.assign(new Error('Request failed'),{status:res.status,json});}
    return json;
  }

  function fmtMoney(n){
    const x = Number(n||0);
    return x.toLocaleString('cs-CZ',{minimumFractionDigits:2,maximumFractionDigits:2});
  }

  function cartItemRow(it){
    const vatPct = Number(it.vat_percent||0);
    const priceNet = Number(it.price_net||it.price||0);
    const priceGross = Number(it.price_gross||priceNet);
    const qty = Number(it.quantity||0);
    const lineNet = priceNet*qty;
    const lineGross = priceGross*qty;

    const priceHtml = (vatPct>0)
      ? `<strong>${fmtMoney(priceGross)} Kč</strong><br><span class="hint">${fmtMoney(priceNet)} Kč bez DPH (DPH ${vatPct}%)</span>`
      : `<strong>${fmtMoney(priceNet)} Kč</strong>`;

    const lineHtml = (vatPct>0)
      ? `<strong>${fmtMoney(lineGross)} Kč</strong><br><span class="hint">${fmtMoney(lineNet)} Kč bez DPH</span>`
      : `<strong>${fmtMoney(lineNet)} Kč</strong>`;

    return `<tr data-id="${it.id}">
      <td>${escapeHtml(it.name)}</td>
      <td>${priceHtml}</td>
      <td><input type="number" min="1" step="1" value="${it.quantity}" class="shop-qty"></td>
      <td>${lineHtml}</td>
      <td><button class="shop-btn shop-btn-small shop-remove">×</button></td>
    </tr>`;
  }

  function escapeHtml(str){
    return String(str||'').replace(/[&<>"]/g, s=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[s]));
  }

  async function refreshFloating(){
    const box = q('#shop-floating-cart');
    if(!box) return;
    const data = await get(API_CART + '?action=get');
    const count = (data.summary && data.summary.count) ? data.summary.count : 0;
    q('#shop-cart-count') && (q('#shop-cart-count').textContent = String(count));
    box.style.display = count>0 ? 'block' : 'none';
  }

  async function renderDrawer(){
    const body = q('#shop-cart-drawer-body');
    if(!body) return;
    const data = await get(API_CART + '?action=get');
    const items = Object.values(data.cart||{});
    if(items.length===0){
      body.innerHTML = '<div class="shop-empty">Košík je prázdný.</div>';
      return;
    }
    let html = '<table class="shop-cart-table"><thead><tr><th>Produkt</th><th>Ks</th><th></th></tr></thead><tbody>';
    for(const it of items){
      html += `<tr data-id="${it.id}"><td>${escapeHtml(it.name)}</td><td>${it.quantity}</td><td><button class="shop-btn shop-btn-small shop-remove">×</button></td></tr>`;
    }
    html += '</tbody></table>';
    html += `<div class="shop-cart-summary">Celkem: <strong>${fmtMoney(data.summary.total_gross||data.summary.total)} Kč</strong></div>`;
    body.innerHTML = html;

    qa('.shop-remove', body).forEach(btn=>{
      btn.addEventListener('click', async (e)=>{
        const tr = e.target.closest('tr');
        const id = tr ? tr.getAttribute('data-id') : 0;
        await post(API_CART,{action:'remove',product_id:id});
        await refreshFloating();
        await renderDrawer();
      });
    });
  }

  function drawerOpen(){
    const d = q('#shop-cart-drawer');
    if(!d) return;
    d.style.display = 'block';
    renderDrawer().catch(()=>{});
  }
  function drawerClose(){
    const d = q('#shop-cart-drawer');
    if(!d) return;
    d.style.display = 'none';
  }

  async function bindAddButtons(){
    qa('[data-shop-add]').forEach(btn=>{
      btn.addEventListener('click', async ()=>{
        const pid = btn.getAttribute('data-shop-add');
        try{
          await post(API_CART,{action:'add',product_id:pid});
          await refreshFloating();
          drawerOpen();
        }catch(e){
          alert('Nepodařilo se přidat do košíku.');
        }
      });
    });
  }

  async function renderCartPage(){
    const holder = q('#shop-cart-page-body');
    if(!holder) return;

    const [cart, meta] = await Promise.all([
      get(API_CART + '?action=get'),
      get(API_META)
    ]);

    const items = Object.values(cart.cart||{});
    if(items.length===0){
      holder.innerHTML = '<div class="shop-empty">Košík je prázdný.</div>';
      return;
    }

    const shipping = meta.shipping_methods || [];
    const accounts = meta.payment_accounts || [];
    const codFee = Number(meta.cod_fee||0);

    const hasAccounts = accounts.length>0;

    let html = '';
    if(shipping.length===0){ shipping.push({id:0,name:'Bez dopravy',price:0}); }
    html += '<table class="shop-cart-table"><thead><tr><th>Produkt</th><th>Cena</th><th>Množství</th><th>Mezisoučet</th><th></th></tr></thead><tbody>';
    for(const it of items) html += cartItemRow(it);
    html += '</tbody></table>';

    html += '<div class="shop-cart-actions">'
      + '<button class="shop-btn" id="shop-clear">Vyprázdnit</button>'
      + '</div>';

    html += '<h3 style="margin-top:18px;">Doprava</h3>';
    html += '<select id="shop-shipping" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:12px">';
    for(const s of shipping){
      html += `<option value="${s.id}" data-price="${s.price}">${escapeHtml(s.name)} — ${fmtMoney(s.price)} Kč</option>`;
    }
    html += '</select>';

    html += '<h3 style="margin-top:18px;">Platba</h3>';
    html += '<div id="shop-payments" style="display:flex;gap:14px;flex-wrap:wrap">'
      + `<label data-payment="fio_qr"><input type="radio" name="shop_pay" value="fio_qr" checked> QR platba (převod)</label>`
      + `<label data-payment="cod"><input type="radio" name="shop_pay" value="cod"> Dobírka (+${fmtMoney(codFee)} Kč)</label>`
      + `<label data-payment="cash" style="display:none"><input type="radio" name="shop_pay" value="cash"> Platba při převzetí</label>`
      + '</div>';

    html += '<div id="shop-account-wrap" style="margin-top:10px;">'
      + '<label>Účet pro platbu</label>'
      + '<select id="shop-account" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:12px">';
    const defAcc = Number(meta.default_payment_account_id||0);
    if(!hasAccounts){ html += `<option value="0">(Není nastaven žádný účet)</option>`; }
    for(const a of accounts){
      const label = `${a.account_name} (${a.account_number}/${a.bank_code})`;
      html += `<option value="${a.id}" ${defAcc===Number(a.id)?'selected':''}>${escapeHtml(label)}</option>`;
    }
    html += '</select></div>';

    html += '<h3 style="margin-top:18px;">Kontaktní údaje</h3>';
    html += '<div class="shop-checkout-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:12px">'
      + '<input id="shop-name" type="text" placeholder="Jméno a příjmení" required>'
      + '<input id="shop-email" type="email" placeholder="E-mail" required>'
      + '<input id="shop-tel" type="text" placeholder="Telefon" required>'
      + '<input id="shop-ad1" type="text" placeholder="Adresa" required>'
      + '<input id="shop-ad2" type="text" placeholder="Město" required>'
      + '<input id="shop-ad3" type="text" placeholder="PSČ" required>'
      + '</div>';

    html += '<div class="shop-cart-summary" id="shop-total-box"></div>';

    html += '<div style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap">'
      + '<button class="shop-btn" id="shop-submit">Odeslat objednávku</button>'
      + '</div>';

    holder.innerHTML = html;

    // Předvyplnění z pluginu Uživatelé (pokud je uživatel přihlášen)
    try{
      if(meta && meta.client && meta.client.logged_in){
        const c = meta.client || {};
        const fullName = [c.first_name||'', c.last_name||''].join(' ').trim();
        const mail = (c.contact_email && String(c.contact_email).trim()) ? String(c.contact_email).trim() : (c.email||'');
        if(q('#shop-name') && fullName) q('#shop-name').value = fullName;
        if(q('#shop-email') && mail) q('#shop-email').value = mail;
        if(q('#shop-tel') && c.phone) q('#shop-tel').value = c.phone;

        let ad1 = '';
        if(c.street) ad1 = (String(c.street) + ' ' + (c.street_no?String(c.street_no):'')).trim();
        if(!ad1 && c.address) ad1 = String(c.address).split(/\r?\n/)[0] || '';
        if(q('#shop-ad1') && ad1) q('#shop-ad1').value = ad1;

        let ad2 = (c.city||'').toString().trim();
        if(!ad2 && c.address){
          const l = String(c.address).split(/\r?\n/);
          ad2 = (l[1]||'').trim();
        }
        if(q('#shop-ad2') && ad2) q('#shop-ad2').value = ad2;

        let ad3 = (c.zip||'').toString().trim();
        if(!ad3 && c.address){
          const l = String(c.address).split(/\r?\n/);
          ad3 = (l[2]||'').trim();
        }
        if(q('#shop-ad3') && ad3) q('#shop-ad3').value = ad3;

        const grid = q('.shop-checkout-grid', holder);
        if(grid){
          const info = document.createElement('div');
          info.className = 'shop-info';
          info.style.margin = '10px 0 6px 0';
          info.textContent = 'Přihlášený uživatel – údaje jsou předvyplněné z profilu.';
          holder.insertBefore(info, grid);
        }
      }
    }catch(e){}

    function calcTotal(){
      // položky z API už obsahují net/vat/gross
      let itemsNet = 0, itemsVat = 0, itemsGross = 0;
      for(const it of items){
        itemsNet += Number(it.line_net ?? (Number(it.price||0)*Number(it.quantity||0)));
        itemsVat += Number(it.line_vat ?? 0);
        itemsGross += Number(it.line_gross ?? (Number(it.price_gross||it.price||0)*Number(it.quantity||0)));
      }

      const shipSel = q('#shop-shipping');
      let shipPrice = 0;
      if(shipSel && shipSel.options && shipSel.options.length>0){
        const opt = shipSel.options[shipSel.selectedIndex] || shipSel.options[0];
        shipPrice = Number(opt.getAttribute('data-price')||0);
      }

      const pay = q('input[name=shop_pay]:checked')?.value || 'fio_qr';
      const codAdd = (pay==='cod') ? codFee : 0;

      // doprava + dobírka zatím bez DPH (0%)
      const totalNet = itemsNet + shipPrice + codAdd;
      const totalVat = itemsVat;
      const totalGross = itemsGross + shipPrice + codAdd;

      return {itemsNet, itemsVat, itemsGross, shipPrice, pay, codAdd, totalNet, totalVat, totalGross};
    }


    function updatePaymentsByShipping(){
      const shipSel = q('#shop-shipping');
      if(!shipSel || !shipSel.options || shipSel.options.length===0) return;

      const opt = shipSel.options[shipSel.selectedIndex] || shipSel.options[0];
      const shipName = String(opt.textContent||opt.innerText||'').toLowerCase();
      const isPickup = shipName.includes('osobní odběr') || shipName.includes('osobni odber');

      const lblCod  = q('[data-payment="cod"]', holder);
      const lblCash = q('[data-payment="cash"]', holder);

      if(lblCod)  lblCod.style.display  = isPickup ? 'none' : '';
      if(lblCash) lblCash.style.display = isPickup ? '' : 'none';

      const payCod  = q('input[name=shop_pay][value=cod]', holder);
      const payCash = q('input[name=shop_pay][value=cash]', holder);
      const payFio  = q('input[name=shop_pay][value=fio_qr]', holder);

      // pokud byl vybraný COD a přepneme na osobní odběr -> přepni na cash
      if(isPickup && payCod && payCod.checked && payCash){
        payCash.checked = true;
      }
      // pokud nejsme pickup a je vybraná cash -> vrať na fio_qr (nebo nech, ale cash by se neměla používat)
      if(!isPickup && payCash && payCash.checked && payFio){
        payFio.checked = true;
      }
    }

    function renderTotal(){
      const x = calcTotal();

      let vatBreak = '';
      const vats = (cart.summary && Array.isArray(cart.summary.vats)) ? cart.summary.vats : [];
      if((cart.summary && cart.summary.vat_enabled) && vats.length){
        vatBreak += '<div style="margin-top:8px;font-size:12px;color:#6b7280">';
        vatBreak += `<div>Základ: <strong>${fmtMoney(x.totalNet)} Kč</strong> &nbsp;|&nbsp; DPH: <strong>${fmtMoney(x.totalVat)} Kč</strong></div>`;
        for(const g of vats){
          const p = Number(g.percent||0);
          vatBreak += `<div>DPH ${p}%: ${fmtMoney(g.vat)} Kč (základ ${fmtMoney(g.base)} Kč)</div>`;
        }
        vatBreak += '</div>';
      }

      q('#shop-total-box').innerHTML = `Celkem k úhradě: <strong>${fmtMoney(x.totalGross)} Kč</strong>`
        + `<div style="color:#6b7280;font-size:12px;margin-top:6px">Doprava: ${fmtMoney(x.shipPrice)} Kč${(x.pay==='cod' && codFee>0)?`, dobírka: ${fmtMoney(codFee)} Kč`:''}${x.pay==='cash'?`, platba při převzetí`:''}</div>`
        + vatBreak;

      const wrap = q('#shop-account-wrap');
      if(wrap) wrap.style.display = (x.pay==='fio_qr') ? 'block' : 'none';
      const submit = q('#shop-submit');
      if(submit){
        if(x.pay==='fio_qr' && !hasAccounts){
          submit.disabled = true;
          submit.title = 'Nejdřív nastav účet pro platbu v administraci.';
        } else {
          submit.disabled = false;
          submit.title = '';
        }
      }
    }

    updatePaymentsByShipping();
    renderTotal();
    const shipEl = q('#shop-shipping');
    shipEl && shipEl.addEventListener('change', ()=>{ updatePaymentsByShipping(); renderTotal(); });
    qa('input[name=shop_pay]').forEach(r=>r.addEventListener('change', renderTotal));

    // qty changes
    qa('.shop-qty', holder).forEach(inp=>{
      inp.addEventListener('change', async (e)=>{
        const tr = e.target.closest('tr');
        const id = tr.getAttribute('data-id');
        const qty = Math.max(1, parseInt(e.target.value||'1',10));
        await post(API_CART,{action:'update',product_id:id,quantity:qty});
        window.location.reload();
      });
    });

    qa('.shop-remove', holder).forEach(btn=>{
      btn.addEventListener('click', async (e)=>{
        const tr = e.target.closest('tr');
        const id = tr.getAttribute('data-id');
        await post(API_CART,{action:'remove',product_id:id});
        window.location.reload();
      });
    });

    q('#shop-clear').addEventListener('click', async ()=>{
      await post(API_CART,{action:'clear'});
      window.location.reload();
    });

    q('#shop-submit').addEventListener('click', async ()=>{
      const name = q('#shop-name').value.trim();
      const email = q('#shop-email').value.trim();
      const telephone = q('#shop-tel').value.trim();
      const adress1 = q('#shop-ad1').value.trim();
      const adress2 = q('#shop-ad2').value.trim();
      const adress3 = q('#shop-ad3').value.trim();
      if(!name || !email || !telephone || !adress1 || !adress2 || !adress3){
        alert('Vyplň prosím všechny kontaktní údaje.');
        return;
      }

      const x = calcTotal();
      const shipping_id = q('#shop-shipping').value;
      const payment_method = x.pay;
      const payment_account_id = (payment_method==='fio_qr') ? (q('#shop-account')?.value || 0) : 0;

      try{
        q('#shop-submit').disabled = true;

        const res = await post(API_CHECKOUT,{
          name,email,telephone,adress1,adress2,adress3,
          shipping_id,
          payment_method,
          payment_account_id
        });

        if(!res.ok){
          alert('Nepodařilo se vytvořit objednávku.');
          return;
        }

        const o = res.order;

        let out = `<h3>Objednávka vytvořena ✅</h3>`
          + `<div class="shop-cart-summary">Číslo objednávky: <strong>${escapeHtml(o.order_number)}</strong><br>`
          + `Celkem: <strong>${fmtMoney(o.total)} Kč</strong><br>`
          + `Stav: <strong>${escapeHtml(o.status)}</strong></div>`;

        if(o.payment_method === 'fio_qr'){
          out += `<div id="shop-qr" style="margin-top:12px"></div>`;
        }

        holder.innerHTML = out;

        if(o.payment_method === 'fio_qr'){
          const el = q('#shop-qr', holder);
          if(el){
            el.innerHTML = '';

            if(res.qr && res.qr.image_url){
              let url = String(res.qr.image_url);

              // normalizace: když někde vznikne //plugins..., udělej z toho /plugins...
              if(url.startsWith('//')) url = '/' + url.replace(/^\/+/, '');

              const img = new Image();
              img.alt = 'QR';
              img.width = 220;
              img.height = 220;
              img.src = url;
              el.appendChild(img);

            } else if(window.QRCode && res.qr && res.qr.payload){
              new QRCode(el,{text: res.qr.payload, width: 220, height: 220});
            } else if(res.qr && res.qr.payload){
              const img = new Image();
              img.alt = 'QR';
              img.width = 220;
              img.height = 220;
              img.src = 'https://chart.googleapis.com/chart?chs=220x220&cht=qr&chl=' + encodeURIComponent(res.qr.payload);
              el.appendChild(img);

              const hint = document.createElement('div');
              hint.className = 'hint';
              hint.style.marginTop = '8px';
              hint.textContent = '(Fallback přes Google Charts)';
              el.appendChild(hint);
            }
          }
        }

        await refreshFloating();

      }catch(e){
        const err = e.json && e.json.error ? e.json.error : '';
        if(err==='out_of_stock'){
          alert('Nedostatek skladu u produktu ID: ' + (e.json.product_id||'?') + '. Dostupné: ' + (e.json.available||0));
        } else {
          alert('Chyba při vytváření objednávky.');
        }
      }finally{
        q('#shop-submit') && (q('#shop-submit').disabled = false);
      }
    });
  }

  function bindOrderToggles(){
    qa('.shop-order-toggle').forEach(btn=>{
      btn.addEventListener('click', ()=>{
        const id = btn.getAttribute('data-target');
        const row = id ? document.getElementById(id) : null;
        if(!row) return;
        const isOpen = row.style.display === 'table-row';
        row.style.display = isOpen ? 'none' : 'table-row';
        btn.textContent = isOpen ? 'Detail' : 'Skrýt';
      });
    });
  }

function init(){
    bindAddButtons().catch(()=>{});
    refreshFloating().catch(()=>{});

    const open = q('#shop-cart-open');
    const close = q('#shop-cart-close');
    open && open.addEventListener('click', drawerOpen);
    close && close.addEventListener('click', drawerClose);

    // close drawer on outside click
    document.addEventListener('click', (e)=>{
      const d = q('#shop-cart-drawer');
      const b = q('#shop-floating-cart');
      if(!d || d.style.display!=='block') return;
      if(d.contains(e.target) || (b && b.contains(e.target))) return;
      drawerClose();
    });

    // product gallery: click thumb to swap main image
    qa('.shop-gallery__img').forEach(img=>{
      img.addEventListener('click', ()=>{
        const main = q('.shop-product-detail__main');
        if(main){ main.src = img.getAttribute('src'); }
        qa('.shop-gallery__img').forEach(i=>i.classList.remove('is-active'));
        img.classList.add('is-active');
      });
    });

    renderCartPage().catch(()=>{});
  }

  document.addEventListener('DOMContentLoaded', init);
})();
