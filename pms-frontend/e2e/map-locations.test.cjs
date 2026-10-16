const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const ts = require('typescript');

// Exercise the actual Pinia getter without mounting a browser or making API requests.
const compiled = ts.transpileModule(fs.readFileSync('src/store/map.ts', 'utf8'), {
  compilerOptions: { module: ts.ModuleKind.CommonJS },
}).outputText;
const context = {
  exports: {},
  require: (name) => name === 'pinia' ? { defineStore: (_, options) => options } : { emptyLocation: () => ({}) },
};
vm.runInNewContext(compiled, context);
const store = context.exports.useMapStore;

test('each coordinate pair becomes a pin while totals count records once', () => {
  const project = {
    id: 1, location: { latitude: null, longitude: null },
    additional_locations: [
      { latitude: 0, longitude: 121, region_name: 'First site' },
      { latitude: 14.5, longitude: 120.8, region_name: 'Second site' },
      { latitude: null, longitude: null },
    ],
  };
  const state = { mapProjects: [project] };
  const pins = store.getters.plottableProjects(state);
  assert.equal(pins.length, 2);
  assert.equal(pins[0].location.latitude, 0);
  assert.equal(pins[1].location.longitude, 120.8);
  assert.equal(pins[0].id, 1);
  assert.equal(store.getters.totalProjects(state), 1);
  project.location = { latitude: 14.6, longitude: 121.1 };
  assert.equal(store.getters.plottableProjects(state).length, 3);
});
