( function( wp ) {
    const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
    const { __ } = wp.i18n;
    const { useEffect, useCallback } = wp.element;
    const { usePaymentMethodInterface } = window.wc.blocksCheckout;

    const MpesaPaymentMethod = () => {
        const { paymentMethodData, setPaymentMethodData } = usePaymentMethodInterface();

        useEffect( () => {
            console.log('M-Pesa Payment Method loaded');
        }, [] );

        const handlePhoneChange = useCallback( (event) => {
            const phone = event.target.value;
            setPaymentMethodData({ mpesa_phone: phone });
            console.log('M-Pesa phone input changed: ' + phone);
        }, [setPaymentMethodData] );

        return (
            <div>
                <p>{ __( 'Pay securely with M-Pesa via STK Push.', 'woocommerce-mpesa-gateway' ) }</p>
                <label htmlFor="mpesa_phone">
                    { __( 'Phone Number', 'woocommerce-mpesa-gateway' ) } <span className="required">*</span>
                </label>
                <input
                    type="text"
                    id="mpesa_phone"
                    name="mpesa_phone"
                    placeholder="e.g., 254712345678 or 0712345678"
                    required
                    className="input-text"
                    onChange={ handlePhoneChange }
                />
            </div>
        );
    };

    registerPaymentMethod( {
        name: 'woocommerce_mpesa',
        label: __( 'M-Pesa', 'woocommerce-mpesa-gateway' ),
        ariaLabel: __( 'M-Pesa Payment', 'woocommerce-mpesa-gateway' ),
        content: <MpesaPaymentMethod />,
        edit: <MpesaPaymentMethod />,
        canMakePayment: () => true,
        supports: {
            features: [ 'products' ],
        },
    } );
} )( window.wp );