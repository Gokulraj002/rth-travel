/*
 * Quotations module (loaded on every admin page — everything is scoped to its own data attributes).
 *   <form data-quote-builder>      live line amounts + subtotal / discount / GST / grand total,
 *                                  customer "existing / new" toggle, "Load itinerary from package",
 *                                  trip nights + auto end date.
 *   [data-quote-copy="url"]        copy the share link.
 *   <form data-quote-wa>           (opens WhatsApp in a new tab) → reload this page so the "Sent" status shows.
 * The server recalculates every total on save — these numbers are only a preview.
 */
(() => {
  "use strict";

  const base = document.body.dataset.base || "";
  const toast = (m, t) => (window.rthToast ? window.rthToast(m, t) : undefined);
  const money = new Intl.NumberFormat("en-IN", { style: "currency", currency: "INR", minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const fmt = (n) => money.format(Number.isFinite(n) ? n : 0);
  const num = (el) => {
    const v = parseFloat(String(el?.value ?? "").replace(/,/g, ""));
    return Number.isFinite(v) ? v : 0;
  };
  const round2 = (n) => Math.round((n + Number.EPSILON) * 100) / 100;

  // ── Copy share link (detail page) ─────────────────────────────────────────
  document.addEventListener("click", (ev) => {
    const btn = ev.target instanceof Element ? ev.target.closest("[data-quote-copy]") : null;
    if (!btn) return;
    ev.preventDefault();
    const text = btn.getAttribute("data-quote-copy") || "";
    const done = () => toast("Link copied — paste it in WhatsApp or SMS");
    if (navigator.clipboard?.writeText) {
      navigator.clipboard.writeText(text).then(done, () => toast("Could not copy — select the link and copy it", "error"));
    } else {
      const input = document.querySelector("[data-quote-link]");
      if (input) { input.select(); document.execCommand("copy"); done(); }
    }
  });

  // ── WhatsApp share: refresh status after the new tab opens ────────────────
  document.querySelectorAll("form[data-quote-wa]").forEach((form) => {
    form.addEventListener("submit", () => { setTimeout(() => window.location.reload(), 1500); });
  });

  // ── Builder ───────────────────────────────────────────────────────────────
  const form = document.querySelector("form[data-quote-builder]");
  if (!form) return;

  const itemsWrap = form.querySelector("[data-quote-items]");
  const daysWrap = form.querySelector("[data-quote-days]");
  const out = (k) => form.querySelector(`[data-total="${k}"]`);

  const recalc = () => {
    let subtotal = 0;
    itemsWrap?.querySelectorAll("[data-repeater-items] > .rth-repeater-item").forEach((row) => {
      const amount = round2(Math.max(0, num(row.querySelector("[data-qty]"))) * Math.max(0, num(row.querySelector("[data-price]"))));
      subtotal += amount;
      const cell = row.querySelector("[data-amount]");
      if (cell) cell.textContent = fmt(amount);
    });
    subtotal = round2(subtotal);
    const discount = round2(Math.min(Math.max(0, num(form.querySelector("[data-discount]"))), subtotal));
    const pct = Math.min(Math.max(0, num(form.querySelector("[data-tax]"))), 100);
    const tax = round2((subtotal - discount) * pct / 100);
    const total = round2(subtotal - discount + tax);
    if (out("subtotal")) out("subtotal").textContent = fmt(subtotal);
    if (out("discount")) out("discount").textContent = "− " + fmt(discount);
    if (out("tax")) out("tax").textContent = fmt(tax);
    if (out("total")) out("total").textContent = fmt(total);
    const pax = Math.max(1, num(form.querySelector("[data-quote-adults]")));
    if (out("pp")) out("pp").textContent = total > 0 && pax > 1 ? `≈ ${fmt(total / pax)} per adult` : "";
  };

  form.addEventListener("input", recalc);
  form.addEventListener("change", recalc);
  // app.js handles repeater add/remove on click — recalc right after it.
  form.addEventListener("click", (ev) => {
    if (ev.target instanceof Element && ev.target.closest("[data-repeater-add],[data-repeater-remove]")) setTimeout(recalc, 0);
  });

  // ── Customer existing / new toggle ────────────────────────────────────────
  const modes = form.querySelectorAll("[data-customer-mode]");
  const syncMode = () => {
    const mode = [...modes].find((r) => r.checked)?.value || "existing";
    form.querySelectorAll("[data-customer-panel]").forEach((p) => { p.hidden = p.dataset.customerPanel !== mode; });
  };
  modes.forEach((r) => r.addEventListener("change", syncMode));
  syncMode();

  // ── Dates: nights hint + auto end date from the package length ────────────
  const start = form.querySelector("[data-quote-start]");
  const end = form.querySelector("[data-quote-end]");
  const nightsOut = form.querySelector("[data-quote-nights]");
  const pkgSelect = form.querySelector("[data-quote-package]");
  const addDays = (iso, n) => {
    const d = new Date(iso + "T00:00:00");
    d.setDate(d.getDate() + n);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
  };
  const pkgNights = () => parseInt(pkgSelect?.selectedOptions[0]?.dataset.nights || "0", 10) || 0;
  const syncNights = () => {
    if (!nightsOut || !start || !end) return;
    if (start.value && end.value) {
      const n = Math.round((new Date(end.value) - new Date(start.value)) / 86400000);
      nightsOut.textContent = n >= 0 ? `${n} night${n === 1 ? "" : "s"} / ${n + 1} days` : "Ends before it starts";
      nightsOut.classList.toggle("text-danger", n < 0);
    } else {
      nightsOut.textContent = "";
    }
  };
  start?.addEventListener("change", () => {
    if (start.value && end && !end.value && pkgNights() > 0) end.value = addDays(start.value, pkgNights());
    if (end) end.min = start.value || "";
    syncNights();
  });
  end?.addEventListener("change", syncNights);
  syncNights();

  // ── Load itinerary from package (GET /quotations/package/{id}) ────────────
  const addRow = (wrap, values) => {
    const items = wrap.querySelector("[data-repeater-items]");
    const tpl = wrap.querySelector("template[data-repeater-template]");
    if (!items || !tpl) return null;
    const html = tpl.innerHTML.replaceAll("__INDEX__", "p" + Date.now().toString(36) + Math.floor(Math.random() * 1e6));
    items.insertAdjacentHTML("beforeend", html);
    const row = items.lastElementChild;
    Object.entries(values).forEach(([k, v]) => {
      const el = row?.querySelector(`[data-field="${k}"]`);
      if (el) el.value = v ?? "";
    });
    const type = row?.querySelector('select[name$="[type]"]');
    if (type && values.type) type.value = values.type;
    return row;
  };
  const rowHasContent = (row) => [...row.querySelectorAll("input[type=text], textarea")].some((el) => el.value.trim() !== "");
  const renumber = (wrap) => {
    wrap.querySelectorAll("[data-repeater-items] > .rth-repeater-item").forEach((row, i) => {
      row.querySelectorAll("[data-repeater-number]").forEach((n) => { n.textContent = String(i + 1); });
    });
  };

  form.querySelector("[data-quote-load-package]")?.addEventListener("click", async (ev) => {
    const btn = ev.currentTarget;
    const id = parseInt(pkgSelect?.value || "", 10);
    if (!id) { toast("Choose a package first", "error"); pkgSelect?.focus(); return; }
    const dayRows = daysWrap ? [...daysWrap.querySelectorAll("[data-repeater-items] > .rth-repeater-item")] : [];
    const incl = form.querySelector("[data-quote-inclusions]");
    const excl = form.querySelector("[data-quote-exclusions]");
    const hasContent = dayRows.some(rowHasContent) || (incl?.value.trim() ?? "") !== "" || (excl?.value.trim() ?? "") !== "";
    if (hasContent && !window.confirm("Replace the current itinerary, inclusions and exclusions with this package's?")) return;

    btn.disabled = true;
    try {
      const res = await fetch(`${base}/quotations/package/${id}`, { headers: { Accept: "application/json" }, credentials: "same-origin" });
      if (!res.ok) throw new Error(res.status === 404 ? "Package not found" : "Could not load the package");
      const p = await res.json();

      if (daysWrap) {
        dayRows.forEach((r) => r.remove());
        (p.itinerary.length ? p.itinerary : [{ title: "", description: "", stay: "" }]).forEach((d) => addRow(daysWrap, d));
        renumber(daysWrap);
      }
      if (incl) incl.value = (p.inclusions || []).join("\n");
      if (excl) excl.value = (p.exclusions || []).join("\n");

      const title = form.querySelector("[data-quote-title]");
      if (title && !title.value.trim()) title.value = p.title;

      // Add a package price line if there is no priced line yet.
      if (itemsWrap) {
        const rows = [...itemsWrap.querySelectorAll("[data-repeater-items] > .rth-repeater-item")];
        const priced = rows.some((r) => r.querySelector('[data-field="description"]')?.value.trim());
        if (!priced) {
          rows.forEach((r) => r.remove());
          const adults = Math.max(1, num(form.querySelector("[data-quote-adults]")));
          addRow(itemsWrap, { type: "package", description: `${p.title} — ${p.nights}N/${p.days}D, per person`, qty: String(adults), unit_price: String(p.priceFrom || "") });
        }
      }
      if (start?.value && end && !end.value && p.nights > 0) end.value = addDays(start.value, p.nights);
      syncNights();
      recalc();
      toast(`Loaded ${p.itinerary.length} day${p.itinerary.length === 1 ? "" : "s"} from “${p.title}”`);
    } catch (e) {
      toast(e.message || "Could not load the package", "error");
    } finally {
      btn.disabled = false;
    }
  });

  recalc();
})();
