(function () {
  const D = window.FACTURA;
  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => [...el.querySelectorAll(s)];
  const money = n => n.toLocaleString("ro-RO", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const esc = s => String(s ?? "").replace(/[&<>"]/g, c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" }[c]));
  const fmtDate = v => v ? v.split("-").reverse().join(".") : "";

  let lines = []; // {id, svc, name, desc, unit, qty, price}
  let uid = 1;
  const services = {};

  // ---- catalog ----
  const catEl = $("#catalog");
  Object.entries(D.catalog).forEach(([cat, items]) => {
    catEl.insertAdjacentHTML("beforeend", `<div class="fx-cat">${esc(cat)}</div>`);
    items.forEach(s => {
      services[s.id] = s;
      catEl.insertAdjacentHTML("beforeend",
        `<label class="fx-svc"><input type="checkbox" data-svc="${s.id}"><span>${esc(s.denumire)}</span><em>${money(s.pret)} lei/${esc(s.um)}</em></label>`);
    });
  });
  if (!Object.keys(D.catalog).length) catEl.innerHTML = `<div class="fx-empty">Nu ai servicii active. Adaugă din „Servicii și prețuri”.</div>`;

  catEl.addEventListener("change", e => {
    const id = e.target.dataset.svc; if (!id) return;
    if (e.target.checked) {
      const s = services[id];
      lines.push({ id: uid++, svc: id, name: s.denumire, desc: s.descriere || "", unit: s.um, qty: 1, price: s.pret });
    } else {
      lines = lines.filter(l => l.svc !== id);
    }
    renderLines(); render();
  });

  // ---- editable lines ----
  function renderLines() {
    const el = $("#lines");
    $("#count").textContent = lines.length ? `(${lines.length})` : "";
    if (!lines.length) { el.innerHTML = `<div class="fx-empty">Bifează servicii din listă sau adaugă unul personalizat.</div>`; return; }
    el.innerHTML = lines.map(l => `
      <div class="fx-line" data-id="${l.id}">
        <label class="name">Denumire<input data-f="name" value="${esc(l.name)}"></label>
        <button class="del" data-del="${l.id}" title="Scoate" type="button">×</button>
        <label class="desc">Descriere<input data-f="desc" value="${esc(l.desc)}"></label>
        <label>U.M.<input data-f="unit" value="${esc(l.unit)}"></label>
        <label>Cant.<input data-f="qty" type="number" min="0" step="any" value="${l.qty}"></label>
        <label>Preț (lei)<input data-f="price" type="number" min="0" step="any" value="${l.price}"></label>
      </div>`).join("");
  }
  $("#lines").addEventListener("input", e => {
    const f = e.target.dataset.f; if (!f) return;
    const l = lines.find(x => x.id == e.target.closest(".fx-line").dataset.id);
    l[f] = (f === "qty" || f === "price") ? (parseFloat(e.target.value) || 0) : e.target.value;
    render();
  });
  $("#lines").addEventListener("click", e => {
    const id = e.target.dataset.del; if (!id) return;
    const l = lines.find(x => x.id == id);
    if (l.svc) { const cb = $(`#catalog input[data-svc="${l.svc}"]`); if (cb) cb.checked = false; }
    lines = lines.filter(x => x.id != id);
    renderLines(); render();
  });
  $("#addCustom").onclick = () => {
    lines.push({ id: uid++, svc: null, name: "Serviciu", desc: "", unit: "buc", qty: 1, price: 0 });
    renderLines(); render();
    $$("#lines .fx-line:last-child input")[0]?.select();
  };
  $("#reset").onclick = () => {
    lines = []; $$("#catalog input").forEach(c => c.checked = false); renderLines(); render();
  };

  // ---- form values ----
  const vals = () => Object.fromEntries($$("[data-k]").map(i => [i.dataset.k, i.value.trim()]));
  const set = (k, v) => { const i = $(`[data-k="${k}"]`); if (i) i.value = v ?? ""; };
  document.addEventListener("input", e => { if (e.target.dataset.k) render(); });
  document.addEventListener("change", e => { if (e.target.dataset.k) render(); });

  // ---- server calls ----
  async function post(data) {
    const body = new URLSearchParams({ csrf: D.csrf, ...data });
    try {
      const r = await fetch("factura.php", { method: "POST", body, credentials: "same-origin" });
      const j = await r.json();
      return !!j.ok;
    } catch { return false; }
  }

  $("#saveFirma").onclick = async () => {
    const v = vals();
    const firma = Object.fromEntries(Object.entries(v).filter(([k]) => k.startsWith("f_")));
    const msg = $("#firmaMsg");
    msg.className = "fx-msg"; msg.textContent = "Se salvează...";
    const ok = await post({ action: "firma", date: JSON.stringify(firma) });
    msg.className = "fx-msg " + (ok ? "ok" : "bad");
    msg.textContent = ok ? "Salvat." : "Nu s-a salvat. Sesiunea a expirat? Reîncarcă pagina și intră din nou.";
  };

  // ---- clients from admin ----
  const pick = $("#pickClient");
  D.clienti.forEach(c => {
    const label = c.firma ? `${c.firma} (${c.nume})` : c.nume;
    pick.insertAdjacentHTML("beforeend", `<option value="${c.id}">${esc(label)}</option>`);
  });
  pick.onchange = () => {
    const c = D.clienti.find(x => x.id == pick.value);
    if (!c) return;
    set("c_tip", c.firma ? "pj" : "pf");
    set("c_nume", c.firma || c.nume);
    set("c_email", c.email || "");
    ["c_cui", "c_rc", "c_cnp", "c_sediu"].forEach(k => set(k, ""));
    render();
  };

  // ---- invoice render ----
  function render() {
    const v = vals();
    const rate = parseFloat(v.f_tva) || 0;
    const vat = rate > 0;
    $("#page").classList.toggle("novat", !vat);

    let net = 0, tva = 0;
    const rows = lines.map((l, i) => {
      const val = Math.round(l.qty * l.price * 100) / 100;
      const t = Math.round(val * rate) / 100;
      net += val; tva += t;
      return `<tr><td>${i + 1}</td><td>${esc(l.name)}${l.desc ? `<small>${esc(l.desc)}</small>` : ""}</td>
        <td>${esc(l.unit)}</td><td>${l.qty.toLocaleString("ro-RO")}</td><td>${money(l.price)}</td><td>${money(val)}</td>
        <td class="vatcol">${money(t)}</td></tr>`;
    });
    $("#itemsBody").innerHTML = rows.join("") || `<tr class="noitems"><td colspan="7">Niciun serviciu selectat</td></tr>`;

    const out = {
      ...v,
      data_fmt: fmtDate(v.data), termen_fmt: fmtDate(v.termen),
      tva_pct: rate + "%",
      t_net: money(net) + " lei", t_tva: money(tva) + " lei", t_total: money(net + tva) + " lei",
    };
    $$("[data-v]").forEach(el => el.textContent = out[el.dataset.v] ?? "");
    $("#rowPer").style.display = v.perioada ? "" : "none";
    $$(".party:not(.client) dd[data-v], .pay dd[data-v]").forEach(dd => {
      const empty = !dd.textContent.trim();
      dd.hidden = empty; dd.previousElementSibling.hidden = empty;
    });

    const pf = v.c_tip === "pf";
    $$(".pj-only").forEach(el => el.hidden = pf);
    $$(".pf-only").forEach(el => el.hidden = !pf);
    $("#lblNume").textContent = pf ? "Nume și prenume" : "Denumire";
    $("#lblSediu").textContent = pf ? "Adresă (domiciliu)" : "Sediu";
    const c = (pf
      ? [["CNP", v.c_cnp], ["Adresa", v.c_sediu], ["E-mail", v.c_email]]
      : [["CUI", v.c_cui], ["Reg. Com.", v.c_rc], ["Sediu", v.c_sediu], ["E-mail", v.c_email]]
    ).filter(x => x[1]);
    $("#clientDl").innerHTML = c.map(([k, x]) => `<dt>${k}</dt><dd>${esc(x)}</dd>`).join("");

    const notes = [];
    if (!vat) notes.push("Furnizor neplătitor de TVA, scutit conform art. 310 din Codul fiscal.");
    notes.push("Factura este transmisă prin sistemul național RO e-Factura.");
    notes.push("Factura este valabilă fără semnătură și ștampilă (art. 319 alin. (29) Codul fiscal).");
    $("#notes").innerHTML = notes.map(n => `<p>${esc(n)}</p>`).join("");

    document.title = `Factura ${v.serie} ${v.numar}${v.c_nume ? " - " + v.c_nume : ""}`;
  }

  $("#print").onclick = async () => {
    const v = vals();
    await post({ action: "numar", serie: v.serie, numar: v.numar });
    window.print();
  };

  // ---- init ----
  Object.entries(D.firma || {}).forEach(([k, v]) => set(k, v));
  if (!D.firma || !D.firma.f_nume) $("#secFirma").open = true;
  set("serie", D.serie);
  set("numar", D.numar);
  if (D.ultim) $("#ultimHint").textContent = `Ultima factură generată: ${D.serie} ${D.ultim}.`;
  const iso = d => { const z = new Date(d.getTime() - d.getTimezoneOffset() * 60000); return z.toISOString().slice(0, 10); };
  const today = new Date();
  set("data", iso(today));
  const t = new Date(today); t.setDate(t.getDate() + 14); set("termen", iso(t));
  renderLines();
  render();
})();
