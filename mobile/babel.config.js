// Babel-конфигурация мобильного клиента.
// jsxImportSource: 'nativewind' — JSX компилируется в рантайм NativeWind,
// пресет nativewind/babel добавляет css-interop и плагин Reanimated/Worklets.
module.exports = function (api) {
  api.cache(true);

  return {
    presets: [
      ['babel-preset-expo', { jsxImportSource: 'nativewind' }],
      'nativewind/babel',
    ],
  };
};
