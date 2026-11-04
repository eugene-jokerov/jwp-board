// Metro-конфигурация мобильного клиента.
// withNativeWind собирает global.css через Tailwind CLI и подключает рантайм NativeWind.
const { getDefaultConfig } = require('expo/metro-config');
const { withNativeWind } = require('nativewind/metro');

const config = getDefaultConfig(__dirname);

module.exports = withNativeWind(config, { input: './global.css' });
