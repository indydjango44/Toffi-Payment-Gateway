function registerToffiPayment() {
    if (
        window.wc &&
        window.wc.wcBlocksRegistry &&
        window.wc.wcBlocksRegistry.registerPaymentMethod
    ) {
        const {registerPaymentMethod} = window.wc.wcBlocksRegistry;
        const gateways = window.wc_toffi_gateways_data || {};

        Object.entries(gateways).forEach(([key, settings]) => {
            registerPaymentMethod({
                name: key,
                label: settings.title,
                ariaLabel: `${settings.title} payment gateway`,
                canMakePayment: () => true,
                content: wp.element.createElement('div', null, settings.description),
                edit: wp.element.createElement('div', null, settings.description),
                paymentMethodId: key,
                supports: {features: ['products']},
                icon: wp.element.createElement('img', {
                    src: settings.icon_url,
                    alt: settings.title,
                    style: {width: 32, height: 20},
                }),
            });
        });
    } else {
        setTimeout(registerToffiPayment, 50);
    }
}

registerToffiPayment();