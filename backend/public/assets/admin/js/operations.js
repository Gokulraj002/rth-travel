/*
 * Operations module (vendors / vehicles / booking costs). Loaded on every admin page — everything is
 * scoped to [data-ops-service-form] blocks rendered by admin/booking-services/_fields.php:
 *   [data-ops-type]          service type <select>
 *   [data-ops-vendor]        vendor <select>; options carry data-type="hotel|houseboat|…" → filtered by the chosen type
 *   [data-ops-vehicle-field] wrapper shown only for type "transport"
 *   [data-ops-fill-paid]     button: copies the cost price into [data-ops-paid]
 */
(() => {
  "use strict";

  // Service types without a matching vendor type show every vendor.
  const vendorTypeFor = (t) => (["hotel", "houseboat", "transport", "guide", "activity"].includes(t) ? t : "");

  document.querySelectorAll("[data-ops-service-form]").forEach((box) => {
    const type = box.querySelector("[data-ops-type]");
    const vendor = box.querySelector("[data-ops-vendor]");
    const vehicleField = box.querySelector("[data-ops-vehicle-field]");
    if (!type) return;

    // The vendor saved on an existing service always stays selectable, even if its type differs.
    const saved = vendor ? vendor.value : "";
    const apply = () => {
      const want = vendorTypeFor(type.value);
      if (vendor) {
        const opts = Array.from(vendor.options).filter((o) => o.value !== "");
        const matches = opts.filter((o) => !want || o.dataset.type === want).length;
        const showAll = !want || matches === 0; // nothing of this type yet → offer everyone
        opts.forEach((o) => {
          const hide = !showAll && o.dataset.type !== want && o.value !== saved;
          o.hidden = hide;
          o.disabled = hide;
        });
        const sel = vendor.selectedOptions[0];
        if (sel && sel.disabled) vendor.value = "";
      }
      if (vehicleField) {
        const isTransport = type.value === "transport";
        vehicleField.hidden = !isTransport;
        if (!isTransport) {
          const vs = vehicleField.querySelector("select");
          if (vs) vs.value = "";
        }
      }
    };
    type.addEventListener("change", apply);
    apply();

    const fill = box.querySelector("[data-ops-fill-paid]");
    const cost = box.querySelector("[data-ops-cost]");
    const paid = box.querySelector("[data-ops-paid]");
    if (fill && cost && paid) {
      fill.addEventListener("click", () => { paid.value = cost.value; paid.focus(); });
    }
  });
})();
