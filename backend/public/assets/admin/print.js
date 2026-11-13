/* Printable documents: [data-print] → window.print(); [data-copy="text"] → copy to clipboard. (CSP: no inline JS) */
(() => {
  "use strict";
  document.addEventListener("click", (e) => {
    const p = e.target.closest("[data-print]");
    if (p) { e.preventDefault(); window.print(); return; }
    const c = e.target.closest("[data-copy]");
    if (c) {
      e.preventDefault();
      navigator.clipboard?.writeText(c.getAttribute("data-copy") || "").then(() => {
        const old = c.innerHTML; c.textContent = "Copied ✓"; setTimeout(() => { c.innerHTML = old; }, 1500);
      });
    }
  });
})();
