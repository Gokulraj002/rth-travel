/*
 * RTH admin — shared UI behaviours (no build step, no inline scripts; CSP is script-src 'self').
 *
 * Declarative hooks (use these in views instead of writing page scripts):
 *   <form data-confirm="Delete this package?">              → confirm() before submit
 *   <input data-slug-from="#title">                          → auto-slug from another field until edited by hand
 *   <div data-repeater> … <template data-repeater-template>  → repeatable rows (see below)
 *   <div data-image-field> …                                 → image upload + preview (see below)
 *   <tbody data-sortable data-sortable-url="/packages/reorder"> rows: <tr data-id="12"> with [data-sort-handle]
 *   <textarea data-counter="300">                            → live character counter
 *   window.rthToast("Saved", "error"?)                       → small toast message
 *   <input data-upper-from="#name">                          → suggests the other field in CAPITALS until edited by hand
 *   (image fields inside repeater rows added later work too — see "late image fields" at the end)
 *
 * Repeater markup:
 *   <div data-repeater>
 *     <div data-repeater-items> …existing .rth-repeater-item rows… </div>
 *     <template data-repeater-template><div class="rth-repeater-item"> … name="itinerary[__INDEX__][title]" … </div></template>
 *     <button type="button" data-repeater-add>Add</button>
 *   </div>
 *   Inside an item: [data-repeater-remove], [data-repeater-up], [data-repeater-down], [data-repeater-number]
 *   "__INDEX__" in the template is replaced with a unique number. Existing rows may use any unique index.
 *
 * Image field markup:
 *   <div class="rth-image-field" data-image-field>
 *     <img data-image-preview src="https://…current.jpg" alt="">
 *     <input type="hidden" name="image" value="/uploads/…" data-image-input>
 *     <input type="file" accept="image/jpeg,image/png,image/webp" data-image-file>
 *     <button type="button" data-image-clear>Remove</button>
 *   </div>
 *   Uploads go to POST /media/upload (field "file"); response {path, url}.
 */
(() => {
  "use strict";

  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
  const base = document.body.dataset.base || "";

  // ── Toasts ────────────────────────────────────────────────────────────────
  let toastBox;
  window.rthToast = (message, type) => {
    if (!toastBox) {
      toastBox = document.createElement("div");
      toastBox.className = "rth-toasts";
      toastBox.setAttribute("role", "status");
      toastBox.setAttribute("aria-live", "polite");
      document.body.appendChild(toastBox);
    }
    const t = document.createElement("div");
    t.className = "rth-toast" + (type === "error" ? " is-error" : "");
    t.textContent = message;
    toastBox.appendChild(t);
    setTimeout(() => t.remove(), 3500);
  };

  // ── Confirm before submit ─────────────────────────────────────────────────
  document.addEventListener("submit", (ev) => {
    const form = ev.target;
    if (form instanceof HTMLFormElement && form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
      ev.preventDefault();
    }
  });

  // ── Auto slug ─────────────────────────────────────────────────────────────
  const slugify = (s) =>
    s.toLowerCase().normalize("NFKD").replace(/[̀-ͯ]/g, "").replace(/&/g, " and ")
      .replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "").slice(0, 120);

  document.querySelectorAll("[data-slug-from]").forEach((slug) => {
    const src = document.querySelector(slug.dataset.slugFrom);
    if (!src) return;
    let touched = slug.value.trim() !== "";
    slug.addEventListener("input", () => { touched = slug.value.trim() !== ""; });
    src.addEventListener("input", () => { if (!touched) slug.value = slugify(src.value); });
    slug.addEventListener("blur", () => { slug.value = slugify(slug.value); });
  });

  // ── Character counters ────────────────────────────────────────────────────
  document.querySelectorAll("[data-counter]").forEach((el) => {
    const max = parseInt(el.dataset.counter, 10);
    const out = document.createElement("div");
    out.className = "form-hint text-end";
    el.insertAdjacentElement("afterend", out);
    const upd = () => { out.textContent = `${el.value.length} / ${max}`; out.classList.toggle("text-danger", el.value.length > max); };
    el.addEventListener("input", upd);
    upd();
  });

  // ── Repeaters ─────────────────────────────────────────────────────────────
  let uid = Date.now();
  const renumber = (wrap) => {
    wrap.querySelectorAll(":scope [data-repeater-items] > .rth-repeater-item").forEach((item, i) => {
      item.querySelectorAll("[data-repeater-number]").forEach((n) => { n.textContent = String(i + 1); });
    });
  };
  document.querySelectorAll("[data-repeater]").forEach((wrap) => {
    const items = wrap.querySelector("[data-repeater-items]");
    const tpl = wrap.querySelector("template[data-repeater-template]");
    if (!items || !tpl) return;
    wrap.addEventListener("click", (ev) => {
      const btn = ev.target.closest("button");
      if (!btn || !wrap.contains(btn)) return;
      const item = btn.closest(".rth-repeater-item");
      if (btn.hasAttribute("data-repeater-add")) {
        const html = tpl.innerHTML.replaceAll("__INDEX__", String(uid++));
        items.insertAdjacentHTML("beforeend", html);
        const last = items.lastElementChild;
        last?.querySelector("input, textarea, select")?.focus();
      } else if (item && btn.hasAttribute("data-repeater-remove")) {
        item.remove();
      } else if (item && btn.hasAttribute("data-repeater-up") && item.previousElementSibling) {
        item.parentNode.insertBefore(item, item.previousElementSibling);
      } else if (item && btn.hasAttribute("data-repeater-down") && item.nextElementSibling) {
        item.parentNode.insertBefore(item.nextElementSibling, item);
      } else {
        return;
      }
      renumber(wrap);
    });
    renumber(wrap);
  });

  // ── Image upload fields ───────────────────────────────────────────────────
  document.querySelectorAll("[data-image-field]").forEach((field) => {
    const input = field.querySelector("[data-image-input]");
    const preview = field.querySelector("[data-image-preview]");
    const file = field.querySelector("[data-image-file]");
    const clear = field.querySelector("[data-image-clear]");
    file?.addEventListener("change", async () => {
      const f = file.files?.[0];
      if (!f) return;
      if (f.size > 8 * 1024 * 1024) { rthToast("Image is larger than 8 MB.", "error"); file.value = ""; return; }
      const body = new FormData();
      body.append("file", f);
      body.append("_token", csrf);
      field.classList.add("is-uploading");
      try {
        const res = await fetch(base + "/media/upload", { method: "POST", body, headers: { "X-CSRF-Token": csrf, Accept: "application/json" }, credentials: "same-origin" });
        const json = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(json.error || "Upload failed");
        if (input) input.value = json.path;
        if (preview) preview.src = json.url;
        rthToast("Image uploaded");
      } catch (e) {
        rthToast(e.message || "Upload failed", "error");
      } finally {
        field.classList.remove("is-uploading");
        file.value = "";
      }
    });
    clear?.addEventListener("click", () => {
      if (input) input.value = "";
      if (preview) preview.src = "";
    });
  });

  // ── Drag-to-reorder table rows ────────────────────────────────────────────
  document.querySelectorAll("[data-sortable]").forEach((tbody) => {
    let dragging = null;
    tbody.querySelectorAll("tr[data-id]").forEach((row) => {
      const handle = row.querySelector("[data-sort-handle]");
      if (!handle) return;
      handle.addEventListener("mousedown", () => row.setAttribute("draggable", "true"));
      handle.addEventListener("touchstart", () => row.setAttribute("draggable", "true"), { passive: true });
      row.addEventListener("dragstart", (e) => { dragging = row; row.classList.add("rth-dragging"); e.dataTransfer.effectAllowed = "move"; });
      row.addEventListener("dragend", () => {
        row.classList.remove("rth-dragging");
        row.removeAttribute("draggable");
        tbody.querySelectorAll(".rth-drop-target").forEach((r) => r.classList.remove("rth-drop-target"));
        dragging = null;
      });
      row.addEventListener("dragover", (e) => {
        if (!dragging || dragging === row) return;
        e.preventDefault();
        row.classList.add("rth-drop-target");
      });
      row.addEventListener("dragleave", () => row.classList.remove("rth-drop-target"));
      row.addEventListener("drop", async (e) => {
        e.preventDefault();
        row.classList.remove("rth-drop-target");
        if (!dragging || dragging === row) return;
        const rect = row.getBoundingClientRect();
        const after = e.clientY > rect.top + rect.height / 2;
        row.parentNode.insertBefore(dragging, after ? row.nextSibling : row);
        const ids = [...tbody.querySelectorAll("tr[data-id]")].map((r) => parseInt(r.dataset.id, 10));
        try {
          const res = await fetch(base + tbody.dataset.sortableUrl, {
            method: "POST",
            headers: { "Content-Type": "application/json", "X-CSRF-Token": csrf, Accept: "application/json" },
            credentials: "same-origin",
            body: JSON.stringify({ ids }),
          });
          if (!res.ok) throw new Error();
          rthToast("Order saved");
        } catch {
          rthToast("Could not save the new order", "error");
        }
      });
    });
  });
})();

// ── Appended: data-upper-from + late image fields (destinations module) ──────
// data-upper-from: like data-slug-from, but copies the source text in CAPITALS (e.g. display name).
// Late image fields: the image hook above only wires [data-image-field] present at page load; this
// delegated handler wires fields that appear later (e.g. a gallery row added via data-repeater).
(() => {
  "use strict";
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
  const base = document.body.dataset.base || "";
  const toast = (m, t) => (window.rthToast ? window.rthToast(m, t) : undefined);

  document.querySelectorAll("[data-upper-from]").forEach((el) => {
    const src = document.querySelector(el.dataset.upperFrom);
    if (!src) return;
    let touched = el.value.trim() !== "";
    el.addEventListener("input", () => { touched = el.value.trim() !== ""; });
    src.addEventListener("input", () => { if (!touched) el.value = src.value.toUpperCase(); });
  });

  document.querySelectorAll("[data-image-field]").forEach((f) => { f.dataset.imageBound = "1"; });
  const lateField = (el) => {
    const field = el.closest("[data-image-field]");
    return field && !field.dataset.imageBound ? field : null;
  };
  document.addEventListener("change", async (ev) => {
    const file = ev.target;
    if (!(file instanceof HTMLInputElement) || !file.matches("[data-image-file]")) return;
    const field = lateField(file);
    const f = file.files?.[0];
    if (!field || !f) return;
    if (f.size > 8 * 1024 * 1024) { toast("Image is larger than 8 MB.", "error"); file.value = ""; return; }
    const input = field.querySelector("[data-image-input]");
    const preview = field.querySelector("[data-image-preview]");
    const body = new FormData();
    body.append("file", f);
    body.append("_token", csrf);
    field.classList.add("is-uploading");
    try {
      const res = await fetch(base + "/media/upload", { method: "POST", body, headers: { "X-CSRF-Token": csrf, Accept: "application/json" }, credentials: "same-origin" });
      const json = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(json.error || "Upload failed");
      if (input) input.value = json.path;
      if (preview) preview.src = json.url;
      toast("Image uploaded");
    } catch (e) {
      toast(e.message || "Upload failed", "error");
    } finally {
      field.classList.remove("is-uploading");
      file.value = "";
    }
  });
  document.addEventListener("click", (ev) => {
    const btn = ev.target instanceof Element ? ev.target.closest("[data-image-clear]") : null;
    const field = btn ? lateField(btn) : null;
    if (!field) return;
    const input = field.querySelector("[data-image-input]");
    const preview = field.querySelector("[data-image-preview]");
    if (input) input.value = "";
    if (preview) preview.setAttribute("src", "");
  });
})();
