
const PHONE = "5562984939375";
const products = Array.isArray(window.BST_PRODUCTS) ? window.BST_PRODUCTS : [];

function money(value){
  if (typeof value !== "number") return "Consulte";
  return value.toLocaleString("pt-BR",{style:"currency",currency:"BRL"});
}
function iconFor(category=""){
  const c=category.toLowerCase();
  if(c.includes("segurança")) return "📷";
  if(c.includes("rede")) return "🌐";
  if(c.includes("celular")) return "📱";
  if(c.includes("comput")) return "🖥️";
  return "💻";
}
function wa(text){
  return `https://wa.me/${PHONE}?text=${encodeURIComponent(text)}`;
}
function productCard(p){
  const media = p.image
    ? `<img src="${p.image}" alt="${p.name}">`
    : `<div class="product-placeholder">${iconFor(p.category)}</div>`;
  const price = typeof p.price === "number"
    ? `<div class="product-price">${money(p.price)}</div>`
    : `<div class="product-price consult">Preço sob consulta</div>`;
  return `
    <article class="product-card">
      <a class="product-media" href="produto.html?slug=${encodeURIComponent(p.slug)}">${media}</a>
      <div class="product-body">
        <span class="product-category">${p.category}</span>
        <h2>${p.name}</h2>
        <p>${p.short || ""}</p>
        ${price}
        <div class="product-actions">
          <a class="btn btn-outline" href="produto.html?slug=${encodeURIComponent(p.slug)}">Detalhes</a>
          <button class="btn btn-dark" onclick="addToCart('${p.id}')">Adicionar</button>
        </div>
      </div>
    </article>`;
}
function cart(){
  try{return JSON.parse(localStorage.getItem("bst_cart")||"[]")}catch{return []}
}
function saveCart(items){
  localStorage.setItem("bst_cart",JSON.stringify(items));
  updateCartCount();
}
function updateCartCount(){
  const count=cart().reduce((s,i)=>s+i.qty,0);
  document.querySelectorAll("#cart-count").forEach(el=>el.textContent=count);
}
window.addToCart=function(id){
  const p=products.find(x=>x.id===id);
  if(!p)return;
  const items=cart();
  const found=items.find(x=>x.id===id);
  if(found)found.qty+=1;else items.push({id,qty:1});
  saveCart(items);
  alert(`${p.name} adicionado ao carrinho.`);
}
function initMenu(){
  const toggle=document.querySelector(".menu-toggle");
  const nav=document.querySelector(".nav");
  toggle?.addEventListener("click",()=>{
    const open=nav.classList.toggle("open");
    toggle.setAttribute("aria-expanded",String(open));
  });
  document.querySelectorAll(".nav a").forEach(a=>a.addEventListener("click",()=>nav?.classList.remove("open")));
}
function initHome(){
  const el=document.getElementById("home-products");
  if(!el)return;
  el.innerHTML=products.slice(0,6).map(productCard).join("");
}
function initCatalog(){
  const grid=document.getElementById("catalog-grid");
  if(!grid)return;
  const search=document.getElementById("search");
  const category=document.getElementById("category");
  const empty=document.getElementById("catalog-empty");
  [...new Set(products.map(p=>p.category))].sort().forEach(c=>{
    const o=document.createElement("option");o.value=c;o.textContent=c;category.appendChild(o);
  });
  const params=new URLSearchParams(location.search);
  if(params.get("categoria")) category.value=params.get("categoria");
  function render(){
    const q=(search.value||"").toLowerCase().trim();
    const cat=category.value;
    const list=products.filter(p=>{
      const hay=`${p.name} ${p.category} ${p.short} ${p.description}`.toLowerCase();
      return (!q||hay.includes(q))&&(!cat||p.category===cat);
    });
    grid.innerHTML=list.map(productCard).join("");
    empty.hidden=list.length>0;
  }
  search.addEventListener("input",render);
  category.addEventListener("change",render);
  render();
}
function initProduct(){
  const host=document.getElementById("product-detail");
  if(!host)return;
  const slug=new URLSearchParams(location.search).get("slug");
  const p=products.find(x=>x.slug===slug);
  if(!p){host.innerHTML=`<div class="empty-state">Produto não encontrado.</div>`;return;}
  document.title=`${p.name} | Britos Soluções Tecnológicas`;
  const media=p.image?`<img src="${p.image}" alt="${p.name}">`:`<div class="product-placeholder">${iconFor(p.category)}</div>`;
  host.innerHTML=`
    <div class="product-detail">
      <div class="product-detail-media">${media}</div>
      <div>
        <span class="eyebrow">${p.category}</span>
        <h1>${p.name}</h1>
        <p>${p.description||p.short||""}</p>
        <div class="product-price ${typeof p.price==="number"?"":"consult"}">${typeof p.price==="number"?money(p.price):"Preço sob consulta"}</div>
        <ul class="highlights">${(p.highlights||[]).map(h=>`<li>${h}</li>`).join("")}</ul>
        <div class="product-actions">
          <button class="btn btn-dark" onclick="addToCart('${p.id}')">Adicionar ao carrinho</button>
          <a class="btn btn-whatsapp" target="_blank" rel="noopener" href="${wa(`Olá, quero informações sobre ${p.name}.`)}">Consultar no WhatsApp</a>
        </div>
      </div>
    </div>`;
}
function initCart(){
  const host=document.getElementById("cart-items");
  if(!host)return;
  const totalEl=document.getElementById("cart-total");
  function render(){
    const items=cart();
    if(!items.length){host.innerHTML=`<div class="empty-state">Seu carrinho está vazio.</div>`;totalEl.textContent=money(0);return;}
    let total=0;
    host.innerHTML=items.map(i=>{
      const p=products.find(x=>x.id===i.id);if(!p)return "";
      if(typeof p.price==="number") total+=p.price*i.qty;
      return `<div class="cart-row">
        <div><strong>${p.name}</strong><div class="product-category">${p.category}</div><small>${typeof p.price==="number"?money(p.price):"Preço sob consulta"}</small></div>
        <div class="qty"><button data-act="minus" data-id="${p.id}">−</button><strong>${i.qty}</strong><button data-act="plus" data-id="${p.id}">+</button></div>
        <button class="remove" data-act="remove" data-id="${p.id}">Remover</button>
      </div>`;
    }).join("");
    totalEl.textContent=money(total);
    host.querySelectorAll("button[data-act]").forEach(btn=>btn.addEventListener("click",()=>{
      const items=cart();const row=items.find(x=>x.id===btn.dataset.id);
      if(!row)return;
      if(btn.dataset.act==="plus")row.qty++;
      if(btn.dataset.act==="minus")row.qty=Math.max(1,row.qty-1);
      if(btn.dataset.act==="remove")items.splice(items.indexOf(row),1);
      saveCart(items);render();
    }));
  }
  document.getElementById("clear-cart")?.addEventListener("click",()=>{saveCart([]);render();});
  document.getElementById("send-whatsapp")?.addEventListener("click",()=>{
    const items=cart();
    if(!items.length)return alert("Seu carrinho está vazio.");
    const lines=items.map(i=>{
      const p=products.find(x=>x.id===i.id);
      return `• ${i.qty}x ${p?.name||i.id}${typeof p?.price==="number"?` — ${money(p.price*i.qty)}`:" — preço sob consulta"}`;
    });
    const msg=`Olá, gostaria de solicitar orçamento para:\n\n${lines.join("\n")}\n\nPode me informar disponibilidade e condições?`;
    window.open(wa(msg),"_blank","noopener");
  });
  render();
}
document.getElementById("year")?.replaceChildren(document.createTextNode(new Date().getFullYear()));
initMenu();updateCartCount();initHome();initCatalog();initProduct();initCart();
