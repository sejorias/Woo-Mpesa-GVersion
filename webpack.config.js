const defaultConfig = require('@wordpress/scripts/config/webpack.config');
module.exports = {
    ...defaultConfig,
    entry: {
        'mpesa-checkout-block': './assets/src/mpesa-checkout-block.js',
    },
    output: {
        path: __dirname + '/assets/js',
        filename: '[name].min.js',
    },
};

/etc/init.d/php-fpm80 restart

XwUf50ubSO6s8B7zwevsOKVVv0s+ZQgvkjUyzsQ73pOC+zCGukEKxUYzO0vE5U9TsHRzQlh1CPXS2dnA4vZjTS4tjb3s2ty+fWNCE/LBq3hMqd3t4vS7Eh8RDpfrCpnqgMmF9GT81uMo1n5VzmRQJvohzc3nDtaGSYxnGLrIUnElQ/wKgXY44VemB4WdmikFfFVVuPiv19NEKWA4Dzeb0iNPGrY8CpsF5iiQSvu44ZnQ25IL0X+8ivq328ynmkLmlcFOFKhLqV+gcB3qu8mY3kb67Qy6tL66//99yz8F+v6ZmlyYb2kZFdzrpEp0Md3cMCNgNlOeoZ/SIcm6ovaFkg==