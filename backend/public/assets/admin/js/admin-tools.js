/*
 * Admin tools (templates, team, activity, backup) — loaded on every admin page, so every hook
 * below only runs when its data attribute is present. No inline scripts (CSP script-src 'self').
 *
 *   <input type="checkbox" data-tools-autosubmit>   → submits its form when changed (template on/off switch)
 *   <form data-tpl-editor data-tpl-samples='{…}' data-tpl-system='{"name":"channel"}'
 *         data-tpl-original-name="…" data-tpl-original-channel="…">
 *     [data-tpl-channel] select, [data-tpl-name] input, [data-tpl-subject-group], [data-tpl-field="subject|body"],
 *     [data-tpl-insert="{{key}}"] chips (insert at the cursor of the last focused field),
 *     [data-tpl-preview] / [data-tpl-preview-subject] live preview, [data-tpl-rename-warning]
 */
(() => {
  "use strict";

  // ── Auto-submit switches ──────────────────────────────────────────────────
  document.querySelectorAll("[data-tools-autosubmit]").forEach((el) => {
    el.addEventListener("change", () => {
      const form = el.closest("form");
      if (!form) return;
      el.disabled = true;
      if (typeof form.requestSubmit === "function") form.requestSubmit();
      else form.submit();
    });
  });

  // ── Template editor ───────────────────────────────────────────────────────
  const editor = document.querySelector("[data-tpl-editor]");
  if (!editor) return;

  const parse = (s) => { try { return JSON.parse(s || "{}"); } catch { return {}; } };
  const samples = parse(editor.dataset.tplSamples);
  const systemNames = parse(editor.dataset.tplSystem);
  const originalName = editor.dataset.tplOriginalName || "";
  const originalChannel = editor.dataset.tplOriginalChannel || "";

  const channel = editor.querySelector("[data-tpl-channel]");
  const nameInput = editor.querySelector("[data-tpl-name]");
  const subjectGroup = editor.querySelector("[data-tpl-subject-group]");
  const subject = editor.querySelector('[data-tpl-field="subject"]');
  const body = editor.querySelector('[data-tpl-field="body"]');
  const preview = editor.querySelector("[data-tpl-preview]");
  const previewSubject = editor.querySelector("[data-tpl-preview-subject]");
  const previewWrap = editor.querySelector("[data-tpl-preview-wrap]");
  const warning = editor.querySelector("[data-tpl-rename-warning]");

  const render = (text) =>
    String(text || "").replace(/\{\{\s*([a-z0-9_]+)\s*\}\}/gi, (_, key) => {
      const k = key.toLowerCase();
      return Object.prototype.hasOwnProperty.call(samples, k) ? String(samples[k]) : "";
    });

  // WhatsApp-style *bold* / _italic_ / ~strike~ — built with DOM nodes, never innerHTML.
  const appendFormatted = (parent, text, isWhatsApp) => {
    if (!isWhatsApp) {
      parent.appendChild(document.createTextNode(text));
      return;
    }
    const re = /(\*[^*\n]+\*|_[^_\n]+_|~[^~\n]+~)/g;
    let last = 0;
    let m;
    while ((m = re.exec(text)) !== null) {
      if (m.index > last) parent.appendChild(document.createTextNode(text.slice(last, m.index)));
      const tag = m[0][0] === "*" ? "strong" : m[0][0] === "_" ? "em" : "s";
      const el = document.createElement(tag);
      el.textContent = m[0].slice(1, -1);
      parent.appendChild(el);
      last = m.index + m[0].length;
    }
    if (last < text.length) parent.appendChild(document.createTextNode(text.slice(last)));
  };

  const isEmail = () => channel && channel.value === "email";

  const update = () => {
    const email = isEmail();
    if (subjectGroup) subjectGroup.classList.toggle("d-none", !email);
    if (previewWrap) previewWrap.dataset.channel = email ? "email" : "whatsapp";

    if (previewSubject) {
      const s = subject ? render(subject.value).trim() : "";
      previewSubject.classList.toggle("d-none", !email);
      previewSubject.textContent = email ? (s ? "Subject: " + s : "Subject: (empty)") : "";
    }
    if (preview) {
      preview.textContent = "";
      const text = body ? render(body.value) : "";
      if (text.trim() === "") {
        const hint = document.createElement("span");
        hint.className = "text-secondary";
        hint.textContent = "Start typing your message…";
        preview.appendChild(hint);
      } else {
        appendFormatted(preview, text, !email);
      }
    }
    if (warning && nameInput && originalName && systemNames[originalName] === originalChannel) {
      const changed = nameInput.value.trim() !== originalName || (channel && channel.value !== originalChannel);
      warning.classList.toggle("d-none", !changed);
    }
  };

  // Remember the last focused text field so chips insert where the user was typing.
  let target = body;
  [subject, body].forEach((f) => f && f.addEventListener("focus", () => { target = f; }));

  editor.querySelectorAll("[data-tpl-insert]").forEach((chip) => {
    chip.addEventListener("click", () => {
      let field = target;
      if (!field || (field === subject && !isEmail())) field = body;
      if (!field) return;
      const token = chip.dataset.tplInsert || "";
      const start = field.selectionStart ?? field.value.length;
      const end = field.selectionEnd ?? field.value.length;
      const max = parseInt(field.getAttribute("maxlength") || "0", 10);
      if (max && field.value.length - (end - start) + token.length > max) {
        if (window.rthToast) window.rthToast("The message is too long to add this.", "error");
        return;
      }
      field.focus();
      field.setRangeText(token, start, end, "end");
      field.dispatchEvent(new Event("input", { bubbles: true }));
    });
  });

  [channel, nameInput, subject, body].forEach((el) => {
    if (!el) return;
    el.addEventListener("input", update);
    el.addEventListener("change", update);
  });
  update();
})();
