/*
 * Accounts module (business details, GST invoices, reports). No inline JS — hooks:
 *   <select data-acc-state="#codeInput"> options carry data-code   → fills the state code box
 *   <input data-acc-upper>                                          → upper-cases as you type
 *   <select data-acc-autosubmit>                                    → submits its form on change
 *   <form data-acc-invoice data-company-state="…">                 → live invoice totals preview
 *   <form data-acc-range> with [data-acc-preset] + [data-acc-custom] → shows custom dates only for "custom"
 */
(() => {
  "use strict";

  document.querySelectorAll("select[data-acc-state]").forEach((sel) => {
    const out = document.querySelector(sel.dataset.accState);
    if (!out) return;
    const sync = () => { out.value = sel.selectedOptions[0]?.dataset.code || ""; };
    sel.addEventListener("change", sync);
  });

  document.addEventListener("input", (ev) => {
    const el = ev.target;
    if (el instanceof HTMLInputElement && el.hasAttribute("data-acc-upper")) {
      const pos = el.selectionStart;
      el.value = el.value.toUpperCase().replace(/\s+/g, "");
      if (pos !== null) el.setSelectionRange(Math.min(pos, el.value.length), Math.min(pos, el.value.length));
    }
  });

  document.querySelectorAll("select[data-acc-autosubmit]").forEach((sel) => {
    sel.addEventListener("change", () => { if (sel.value && sel.form) sel.form.submit(); });
  });

  // ── Live invoice preview (the server recalculates on save) ────────────────
  const money = (n) => "₹" + (Math.round(n * 100) / 100).toLocaleString("en-IN", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const r2 = (n) => Math.round((n + Number.EPSILON) * 100) / 100;
  const num = (el) => { const v = parseFloat(el?.value ?? ""); return Number.isFinite(v) ? v : 0; };
  const pct = (n) => (Math.round(n * 100) / 100).toString() + "%";

  document.querySelectorAll("form[data-acc-invoice]").forEach((form) => {
    const companyState = (form.dataset.companyState || "").toLowerCase();
    const out = (k) => form.querySelector(`[data-acc-out="${k}"]`);
    const set = (k, v) => { const el = out(k); if (el) el.textContent = v; };

    const recalc = () => {
      let gross = 0;
      form.querySelectorAll("[data-acc-line]").forEach((line) => {
        const amt = r2(num(line.querySelector("[data-acc-qty]")) * num(line.querySelector("[data-acc-rate]")));
        gross += amt;
        const cell = line.querySelector("[data-acc-amount]");
        if (cell) cell.textContent = amt ? money(amt) : "—";
      });
      gross = r2(gross);
      const rate = Math.max(0, num(form.querySelector("[data-acc-tax]")));
      const discount = Math.min(Math.max(0, num(form.querySelector("[data-acc-discount]"))), gross);
      const inclusive = form.querySelector("[data-acc-inclusive]")?.checked;
      const pos = (form.querySelector("[data-acc-pos]")?.value || "").toLowerCase();
      const intra = pos === "" || pos === companyState;
      let taxable, cgst = 0, sgst = 0, igst = 0;
      if (inclusive && rate > 0) {
        const total = r2(gross - discount);
        taxable = r2(total * 100 / (100 + rate));
        const tax = r2(total - taxable);
        if (intra) { cgst = r2(tax / 2); sgst = r2(tax - cgst); } else { igst = tax; }
      } else {
        taxable = r2(gross - discount);
        if (intra) { cgst = sgst = r2(taxable * rate / 200); } else { igst = r2(taxable * rate / 100); }
      }
      set("taxable", money(taxable));
      set("cgst", money(cgst));
      set("sgst", money(sgst));
      set("igst", money(igst));
      set("total", money(r2(taxable + cgst + sgst + igst)));
      set("halfrate", "@ " + pct(rate / 2));
      set("halfrate2", "@ " + pct(rate / 2));
      set("rate", "@ " + pct(rate));
      form.querySelectorAll("[data-acc-intra]").forEach((el) => { el.hidden = !intra; });
      form.querySelectorAll("[data-acc-inter]").forEach((el) => { el.hidden = intra; });
    };

    form.addEventListener("input", recalc);
    form.addEventListener("change", recalc);
    form.addEventListener("click", (ev) => {
      if (ev.target instanceof Element && ev.target.closest("[data-repeater-add],[data-repeater-remove]")) setTimeout(recalc, 0);
    });

    // Hide "new customer" fields while an existing customer is selected.
    const cust = form.querySelector('select[name="customer_id"]');
    const newBox = form.querySelector("[data-acc-newcustomer]");
    if (cust && newBox) {
      const toggle = () => { newBox.hidden = cust.value !== ""; };
      cust.addEventListener("change", toggle);
      toggle();
    }
    recalc();
  });

  // ── Report date range: custom dates only when "Custom" is chosen ──────────
  document.querySelectorAll("form[data-acc-range]").forEach((form) => {
    const preset = form.querySelector("[data-acc-preset]");
    const custom = form.querySelectorAll("[data-acc-custom]");
    if (!preset) return;
    const toggle = () => { custom.forEach((el) => { el.hidden = preset.value !== "custom"; }); };
    preset.addEventListener("change", () => { toggle(); if (preset.value !== "custom") form.submit(); });
    toggle();
  });
})();
