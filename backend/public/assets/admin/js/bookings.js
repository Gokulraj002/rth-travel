/*
 * Bookings module helpers (loaded on every admin page — everything is opt-in via data attributes).
 *   <select data-bk-customer> + <div data-bk-newcustomer>   → hide the "new customer" fields when an existing customer is picked
 *   <select data-bk-package> (options data-title / data-nights) + <input data-bk-title> → suggest title; fill end date from nights
 *   <input data-bk-start> + <input data-bk-end>              → end date can't be before start
 *   <form data-bk-payform data-balance="1234.00"> <input data-bk-amount> <div data-bk-overpay hidden> → warn (not block) on over-payment
 */
(() => {
  "use strict";

  // ── Customer picker ↔ quick-add fields ────────────────────────────────────
  const customer = document.querySelector("[data-bk-customer]");
  const newBox = document.querySelector("[data-bk-newcustomer]");
  if (customer && newBox) {
    const sync = () => newBox.classList.toggle("is-hidden", customer.value !== "");
    customer.addEventListener("change", sync);
    sync();
  }

  // ── Dates ────────────────────────────────────────────────────────────────
  const start = document.querySelector("[data-bk-start]");
  const end = document.querySelector("[data-bk-end]");
  const addDays = (iso, n) => {
    const d = new Date(iso + "T00:00:00");
    if (Number.isNaN(d.getTime())) return "";
    d.setDate(d.getDate() + n);
    const p = (x) => String(x).padStart(2, "0");
    return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`;
  };
  if (start && end) {
    const syncMin = () => {
      end.min = start.value || "";
      if (start.value && end.value && end.value < start.value) end.value = start.value;
    };
    start.addEventListener("change", syncMin);
    syncMin();
  }

  // ── Package → title + end date ───────────────────────────────────────────
  const pkg = document.querySelector("[data-bk-package]");
  const title = document.querySelector("[data-bk-title]");
  if (pkg) {
    let lastSuggested = "";
    const fill = () => {
      const opt = pkg.selectedOptions[0];
      if (!opt || !opt.value) return;
      if (title && (title.value.trim() === "" || title.value === lastSuggested)) {
        title.value = opt.dataset.title || "";
        lastSuggested = title.value;
      }
      const nights = parseInt(opt.dataset.nights || "", 10);
      if (start && end && start.value && !end.value && nights > 0) end.value = addDays(start.value, nights);
    };
    pkg.addEventListener("change", fill);
    if (start && end) {
      start.addEventListener("change", () => {
        const opt = pkg.selectedOptions[0];
        const nights = parseInt(opt?.dataset.nights || "", 10);
        if (opt?.value && nights > 0 && start.value && !end.value) end.value = addDays(start.value, nights);
      });
    }
  }

  // ── Payment form: over-payment warning ────────────────────────────────────
  document.querySelectorAll("[data-bk-payform]").forEach((form) => {
    const amount = form.querySelector("[data-bk-amount]");
    const warn = form.querySelector("[data-bk-overpay]");
    const balance = parseFloat(form.dataset.balance || "0");
    if (!amount || !warn) return;
    const check = () => {
      const v = parseFloat(amount.value || "0");
      warn.hidden = !(v > balance + 0.009);
    };
    amount.addEventListener("input", check);
    check();
  });
})();
