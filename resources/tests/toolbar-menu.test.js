import assert from "node:assert/strict";
import { test } from "vitest";

import {
  isInsideToolbarMenu,
  shouldCloseToolbarMenu,
  toolbarMenu,
  toolbarMenus,
} from "../src/toolbar/menu.js";

test("every bar menu is defined by its wrapper, chip and list selectors", () => {
  assert.deepEqual(toolbarMenus, {
    ajax: { menu: ".ajax-menu", toggle: ".ajax-toggle", wrapper: ".ajax" },
    extensions: {
      menu: ".extensions-menu",
      toggle: ".extensions-toggle",
      wrapper: ".extensions",
    },
  });
});

test("a menu is resolved by its name and nothing else", () => {
  assert.equal(toolbarMenu("ajax"), toolbarMenus.ajax);
  assert.equal(toolbarMenu("extensions"), toolbarMenus.extensions);
  assert.equal(toolbarMenu(null), null);
  assert.equal(toolbarMenu("toString"), null);
});

test("Escape closes only an open menu", () => {
  assert.equal(
    shouldCloseToolbarMenu({ key: "Escape", defaultPrevented: false }, true),
    true,
  );
  assert.equal(
    shouldCloseToolbarMenu({ key: "Enter", defaultPrevented: false }, true),
    false,
  );
  assert.equal(
    shouldCloseToolbarMenu({ key: "Escape", defaultPrevented: true }, true),
    false,
  );
  assert.equal(
    shouldCloseToolbarMenu({ key: "Escape", defaultPrevented: false }, false),
    false,
  );
});

test("outside pointers are told apart from a menu wrapper", () => {
  var wrapper = { className: "ajax menu" };
  var menuItem = { parentElement: wrapper };
  var control = { parentElement: null };
  var closestStub = function (target, selector) {
    if (selector !== ".ajax") {
      return null;
    }

    return target === wrapper || target.parentElement === wrapper
      ? wrapper
      : null;
  };

  assert.equal(
    isInsideToolbarMenu(wrapper, toolbarMenus.ajax, closestStub),
    true,
  );
  assert.equal(
    isInsideToolbarMenu(menuItem, toolbarMenus.ajax, closestStub),
    true,
  );
  assert.equal(
    isInsideToolbarMenu(control, toolbarMenus.ajax, closestStub),
    false,
  );
  assert.equal(
    isInsideToolbarMenu(menuItem, toolbarMenus.extensions, closestStub),
    false,
  );
});
