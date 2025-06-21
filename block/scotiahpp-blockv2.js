document.addEventListener("DOMContentLoaded", function () {
  if (
    window.wc?.wcBlocksRegistry?.registerPaymentMethod &&
    window.wp?.element &&
    window.wc?.wcSettings
  ) {
    const settings = window.wc.wcSettings["scotiahpp_by_hexakode_data"] || {};
    const { createElement } = window.wp.element;

    window.wc.wcBlocksRegistry.registerPaymentMethod({
      name: "scotiahpp_by_hexakode",
      label: createElement("span", null, settings.title || "ScotiaBank HPP"),
      ariaLabel: settings.ariaLabel || "ScotiaBank Payment",
      supports: {
        features: ["products", "subscriptions", "default", "virtual"],
      },
      canMakePayment: () => Promise.resolve(true),
      content: createElement(
        "p",
        null,
        settings.description || "Pay via ScotiaBank HPP"
      ),
      edit: createElement(
        "p",
        null,
        settings.description || "Pay via ScotiaBank HPP"
      ),
      save: null,
    });

    console.log("[ScotiaBank HPP] registered in block checkout");
  }
});
