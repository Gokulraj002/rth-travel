/*
 * CRM module behaviours (loaded on every admin page — everything is scoped to data attributes).
 *
 *   [data-crm-board]           Lead pipeline kanban. Cards [data-crm-card data-id data-stage] can be dragged
 *                              between [data-crm-col] columns (HTML5 drag & drop) or moved with the
 *                              [data-crm-stage-select] menu on each card (keyboard / mobile fallback).
 *                              Moves POST JSON {stage, lost_reason?, lost_note?} to data-stage-url (__ID__ → id)
 *                              with the X-CSRF-Token header. "Lost" asks for a reason ([data-crm-lost-dialog]);
 *                              "Won" offers to create a booking ([data-crm-won-dialog]).
 *   [data-crm-lead-form]       Enquiry page: shows [data-crm-lost-fields] only while [data-crm-stage-field] is "lost".
 */
(() => {
  "use strict";

  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
  const toast = (msg, type) => (window.rthToast ? window.rthToast(msg, type) : window.alert(msg));
  const money = (n) => {
    try {
      return new Intl.NumberFormat("en-IN", { style: "currency", currency: "INR", maximumFractionDigits: 0 }).format(n);
    } catch (_) {
      return "₹" + Math.round(n);
    }
  };

  // ── Enquiry page: lost-reason fields follow the stage ──────────────────────
  document.querySelectorAll("[data-crm-lead-form]").forEach((form) => {
    const stage = form.querySelector("[data-crm-stage-field]");
    const lost = form.querySelector("[data-crm-lost-fields]");
    if (!stage || !lost) return;
    const sync = () => { lost.hidden = stage.value !== "lost"; };
    stage.addEventListener("change", sync);
    sync();
  });

  // ── Kanban board ───────────────────────────────────────────────────────────
  const board = document.querySelector("[data-crm-board]");
  if (!board) return;
  board.classList.add("rth-crm-js"); // hides the no-JS "Move" buttons

  const urlFor = (id) => (board.dataset.stageUrl || "").replace("__ID__", encodeURIComponent(id));
  const listFor = (stage) => board.querySelector('[data-crm-drop][data-stage="' + CSS.escape(stage) + '"]');
  const colFor = (stage) => board.querySelector('[data-crm-col][data-stage="' + CSS.escape(stage) + '"]');

  const bump = (stage, delta, budget) => {
    const col = colFor(stage);
    if (!col) return;
    const count = col.querySelector("[data-crm-count]");
    if (count) count.textContent = String(Math.max(0, (parseInt(count.textContent, 10) || 0) + delta));
    const value = col.querySelector("[data-crm-value]");
    if (value) {
      const v = Math.max(0, (parseFloat(value.dataset.value) || 0) + delta * (budget || 0));
      value.dataset.value = String(v);
      value.textContent = money(v);
      value.hidden = v <= 0;
    }
  };

  const syncEmpty = () => {
    board.querySelectorAll("[data-crm-drop]").forEach((list) => {
      const empty = list.querySelector("[data-crm-empty]");
      if (empty) empty.hidden = list.querySelector("[data-crm-card]") !== null;
    });
  };

  const setSelect = (card, stage) => {
    const sel = card.querySelector("[data-crm-stage-select]");
    if (sel) sel.value = stage;
  };

  // "Why was it lost?" → {lost_reason, lost_note} or null when cancelled.
  const askLost = (name) => {
    const dlg = document.querySelector("[data-crm-lost-dialog]");
    if (!dlg || typeof dlg.showModal !== "function") {
      const r = window.prompt("Why was " + name + " lost? (e.g. Price too high)");
      return Promise.resolve(r === null ? null : { lost_reason: "Other", lost_note: r.slice(0, 150) });
    }
    const reason = dlg.querySelector("[data-crm-lost-reason]");
    const note = dlg.querySelector("[data-crm-lost-note]");
    const error = dlg.querySelector("[data-crm-lost-error]");
    const ok = dlg.querySelector("[data-crm-lost-ok]");
    const cancel = dlg.querySelector("[data-crm-lost-cancel]");
    const label = dlg.querySelector("[data-crm-lost-name]");
    reason.value = "";
    note.value = "";
    error.hidden = true;
    if (label) label.textContent = name;
    // Resolve from the buttons themselves (the dialog "close" event is not dependable in every browser state).
    return new Promise((resolve) => {
      let settled = false;
      const finish = (value) => {
        if (settled) return;
        settled = true;
        ok.removeEventListener("click", onOk);
        cancel?.removeEventListener("click", onCancel);
        dlg.removeEventListener("cancel", onCancel);
        dlg.removeEventListener("close", onCancel);
        if (dlg.open) dlg.close();
        resolve(value);
      };
      const onOk = () => {
        if (!reason.value) {
          error.hidden = false;
          reason.focus();
          return;
        }
        finish({ lost_reason: reason.value, lost_note: note.value.trim() });
      };
      const onCancel = () => finish(null);
      ok.addEventListener("click", onOk);
      cancel?.addEventListener("click", onCancel);
      dlg.addEventListener("cancel", onCancel); // Esc key
      dlg.addEventListener("close", onCancel);
      dlg.showModal();
      reason.focus();
    });
  };

  // Any [data-crm-dialog-close] button closes its dialog.
  document.addEventListener("click", (ev) => {
    const btn = ev.target instanceof Element ? ev.target.closest("[data-crm-dialog-close]") : null;
    const dlg = btn ? btn.closest("dialog") : null;
    if (dlg && dlg.open) dlg.close();
  });

  const showWon = (data, name) => {
    const dlg = document.querySelector("[data-crm-won-dialog]");
    if (!dlg || typeof dlg.showModal !== "function") return;
    const book = dlg.querySelector("[data-crm-won-booking]");
    const enq = dlg.querySelector("[data-crm-won-enquiry]");
    const text = dlg.querySelector("[data-crm-won-text]");
    if (enq) enq.href = data.enquiry_url || "#";
    if (book) {
      book.hidden = !data.booking_url;
      if (data.booking_url) book.href = data.booking_url;
    }
    if (text) {
      text.textContent = data.booking_url
        ? name + " said yes. Create the booking now to record travellers and payments."
        : name + " said yes. Open the enquiry to plan the next step.";
    }
    dlg.showModal();
  };

  let busy = false;
  const move = async (card, stage) => {
    const from = card.dataset.stage;
    if (!stage || stage === from || busy) {
      setSelect(card, from);
      return;
    }
    let payload = { stage };
    if (stage === "lost") {
      const r = await askLost(card.dataset.name || "this lead");
      if (!r) {
        setSelect(card, from);
        return;
      }
      payload = { ...payload, ...r };
    }

    const budget = parseFloat(card.dataset.budget) || 0;
    const oldParent = card.parentElement;
    const oldNext = card.nextSibling;
    const target = listFor(stage);
    if (target) target.insertBefore(card, target.firstChild); // optimistic
    bump(from, -1, budget);
    bump(stage, +1, budget);
    card.dataset.stage = stage;
    setSelect(card, stage);
    syncEmpty();

    busy = true;
    card.classList.add("rth-crm-saving");
    try {
      const res = await fetch(urlFor(card.dataset.id), {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/json", Accept: "application/json", "X-CSRF-Token": csrf },
        body: JSON.stringify(payload),
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data.ok) {
        throw new Error(data.error || (res.status === 419 ? "Your session expired — please refresh the page." : "Could not move the lead. Please try again."));
      }
      toast(data.message || "Saved");
      if (stage === "won") showWon(data, card.dataset.name || "The customer");
    } catch (err) {
      oldParent.insertBefore(card, oldNext);
      bump(stage, -1, budget);
      bump(from, +1, budget);
      card.dataset.stage = from;
      setSelect(card, from);
      syncEmpty();
      toast(err.message, "error");
    } finally {
      busy = false;
      card.classList.remove("rth-crm-saving");
    }
  };

  // Drag & drop
  let dragged = null;
  const clearOver = () => board.querySelectorAll("[data-crm-col].is-over").forEach((c) => c.classList.remove("is-over"));

  board.addEventListener("dragstart", (ev) => {
    const card = ev.target instanceof Element ? ev.target.closest("[data-crm-card]") : null;
    if (!card || ev.target.closest("select, input, button")) return;
    dragged = card;
    card.classList.add("is-dragging");
    ev.dataTransfer.effectAllowed = "move";
    ev.dataTransfer.setData("text/plain", card.dataset.id || "");
  });
  board.addEventListener("dragend", () => {
    if (dragged) dragged.classList.remove("is-dragging");
    dragged = null;
    clearOver();
  });
  board.addEventListener("dragover", (ev) => {
    const col = ev.target instanceof Element ? ev.target.closest("[data-crm-col]") : null;
    if (!col || !dragged) return;
    ev.preventDefault();
    ev.dataTransfer.dropEffect = "move";
    if (!col.classList.contains("is-over")) {
      clearOver();
      col.classList.add("is-over");
    }
  });
  board.addEventListener("dragleave", (ev) => {
    const col = ev.target instanceof Element ? ev.target.closest("[data-crm-col]") : null;
    if (col && !col.contains(ev.relatedTarget)) col.classList.remove("is-over");
  });
  board.addEventListener("drop", (ev) => {
    const col = ev.target instanceof Element ? ev.target.closest("[data-crm-col]") : null;
    if (!col || !dragged) return;
    ev.preventDefault();
    clearOver();
    const card = dragged;
    dragged = null;
    card.classList.remove("is-dragging");
    move(card, col.dataset.stage);
  });

  // Keyboard / touch fallback: the stage menu on each card.
  board.addEventListener("change", (ev) => {
    const sel = ev.target instanceof Element ? ev.target.closest("[data-crm-stage-select]") : null;
    const card = sel ? sel.closest("[data-crm-card]") : null;
    if (card) move(card, sel.value);
  });
  board.addEventListener("submit", (ev) => {
    const form = ev.target instanceof Element ? ev.target.closest("[data-crm-move-form]") : null;
    const card = form ? form.closest("[data-crm-card]") : null;
    if (!card) return;
    ev.preventDefault();
    move(card, form.querySelector("[data-crm-stage-select]")?.value);
  });
})();
