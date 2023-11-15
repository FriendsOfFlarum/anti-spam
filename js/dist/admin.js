/******/ (() => { // webpackBootstrap
/******/ 	// runtime can't be in strict mode because a global variable is assign and maybe created.
/******/ 	var __webpack_modules__ = ({

/***/ "./src/admin/components/AntiSpamSettingsPage.tsx":
/*!*******************************************************!*\
  !*** ./src/admin/components/AntiSpamSettingsPage.tsx ***!
  \*******************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ AntiSpamSettingsPage)
/* harmony export */ });
/* harmony import */ var _babel_runtime_helpers_esm_inheritsLoose__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @babel/runtime/helpers/esm/inheritsLoose */ "./node_modules/@babel/runtime/helpers/esm/inheritsLoose.js");
/* harmony import */ var flarum_admin_app__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! flarum/admin/app */ "flarum/admin/app");
/* harmony import */ var flarum_admin_app__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(flarum_admin_app__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var flarum_admin_components_ExtensionPage__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! flarum/admin/components/ExtensionPage */ "flarum/admin/components/ExtensionPage");
/* harmony import */ var flarum_admin_components_ExtensionPage__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(flarum_admin_components_ExtensionPage__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var flarum_common_components_Link__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! flarum/common/components/Link */ "flarum/common/components/Link");
/* harmony import */ var flarum_common_components_Link__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(flarum_common_components_Link__WEBPACK_IMPORTED_MODULE_3__);




var AntiSpamSettingsPage = /*#__PURE__*/function (_ExtensionPage) {
  (0,_babel_runtime_helpers_esm_inheritsLoose__WEBPACK_IMPORTED_MODULE_0__["default"])(AntiSpamSettingsPage, _ExtensionPage);
  function AntiSpamSettingsPage() {
    return _ExtensionPage.apply(this, arguments) || this;
  }
  var _proto = AntiSpamSettingsPage.prototype;
  _proto.content = function content() {
    var apiRegions = ['closest', 'europe', 'us'];
    return [m("div", {
      className: "FoFAntiSpamSettings"
    }, m("div", {
      className: "container"
    }, m("div", {
      className: "Form"
    }, m("div", {
      className: "Introduction"
    }, m("p", {
      className: "helpText"
    }, flarum_admin_app__WEBPACK_IMPORTED_MODULE_1___default().translator.trans('fof-anti-spam.admin.settings.introduction', {
      a: m((flarum_common_components_Link__WEBPACK_IMPORTED_MODULE_3___default()), {
        href: "https://stopforumspam.com",
        target: "_blank",
        external: true
      })
    }))), m("hr", null), this.buildSettingComponent({
      type: 'select',
      setting: 'fof-anti-spam.regionalEndpoint',
      options: apiRegions.reduce(function (o, p) {
        o[p] = flarum_admin_app__WEBPACK_IMPORTED_MODULE_1___default().translator.trans("fof-anti-spam.admin.settings.region_" + p + "_label");
        return o;
      }, {}),
      label: flarum_admin_app__WEBPACK_IMPORTED_MODULE_1___default().translator.trans('fof-anti-spam.admin.settings.regional_endpoint_label'),
      help: flarum_admin_app__WEBPACK_IMPORTED_MODULE_1___default().translator.trans('fof-anti-spam.admin.settings.regional_endpoint_help'),
      "default": 'closest'
    }), this.buildSettingComponent({
      type: 'boolean',
      setting: 'fof-anti-spam.username',
      label: flarum_admin_app__WEBPACK_IMPORTED_MODULE_1___default().translator.trans('fof-anti-spam.admin.settings.username_label')
    }), this.buildSettingComponent({
      type: 'boolean',
      setting: 'fof-anti-spam.ip',
      label: flarum_admin_app__WEBPACK_IMPORTED_MODULE_1___default().translator.trans('fof-anti-spam.admin.settings.ip_label')
    }), this.buildSettingComponent({
      type: 'boolean',
      setting: 'fof-anti-spam.email',
      label: flarum_admin_app__WEBPACK_IMPORTED_MODULE_1___default().translator.trans('fof-anti-spam.admin.settings.email_label')
    }), this.buildSettingComponent({
      type: 'boolean',
      setting: 'fof-anti-spam.emailhash',
      label: flarum_admin_app__WEBPACK_IMPORTED_MODULE_1___default().translator.trans('fof-anti-spam.admin.settings.email_hash_label'),
      help: flarum_admin_app__WEBPACK_IMPORTED_MODULE_1___default().translator.trans('fof-anti-spam.admin.settings.email_hash_help')
    }), this.buildSettingComponent({
      type: 'number',
      setting: 'fof-anti-spam.frequency',
      label: flarum_admin_app__WEBPACK_IMPORTED_MODULE_1___default().translator.trans('fof-anti-spam.admin.settings.frequency_label'),
      help: flarum_admin_app__WEBPACK_IMPORTED_MODULE_1___default().translator.trans('fof-anti-spam.admin.settings.frequency_help'),
      placeholder: '5',
      required: true
    }), this.buildSettingComponent({
      type: 'number',
      setting: 'fof-anti-spam.confidence',
      label: flarum_admin_app__WEBPACK_IMPORTED_MODULE_1___default().translator.trans('fof-anti-spam.admin.settings.confidence_label'),
      help: flarum_admin_app__WEBPACK_IMPORTED_MODULE_1___default().translator.trans('fof-anti-spam.admin.settings.confidence_help'),
      min: 0,
      max: 100,
      placeholder: '50.0',
      required: true
    }), m("hr", null), m("p", {
      className: "helpText"
    }, flarum_admin_app__WEBPACK_IMPORTED_MODULE_1___default().translator.trans('fof-anti-spam.admin.settings.api_key_text')), this.buildSettingComponent({
      type: 'string',
      setting: 'fof-anti-spam.api_key',
      label: flarum_admin_app__WEBPACK_IMPORTED_MODULE_1___default().translator.trans('fof-anti-spam.admin.settings.api_key_label'),
      help: flarum_admin_app__WEBPACK_IMPORTED_MODULE_1___default().translator.trans('fof-anti-spam.admin.settings.api_key_instructions_text', {
        register: m("a", {
          href: "https://www.stopforumspam.com/forum/register.php"
        }),
        key: m("a", {
          href: "https://www.stopforumspam.com/keys"
        })
      })
    }), m("hr", null), this.submitButton())))];
  };
  return AntiSpamSettingsPage;
}((flarum_admin_components_ExtensionPage__WEBPACK_IMPORTED_MODULE_2___default()));


/***/ }),

/***/ "./src/admin/index.ts":
/*!****************************!*\
  !*** ./src/admin/index.ts ***!
  \****************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var flarum_admin_app__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! flarum/admin/app */ "flarum/admin/app");
/* harmony import */ var flarum_admin_app__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(flarum_admin_app__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _components_AntiSpamSettingsPage__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./components/AntiSpamSettingsPage */ "./src/admin/components/AntiSpamSettingsPage.tsx");


flarum_admin_app__WEBPACK_IMPORTED_MODULE_0___default().initializers.add('fof/anti-spam', function () {
  flarum_admin_app__WEBPACK_IMPORTED_MODULE_0___default().extensionData["for"]('fof-anti-spam').registerPage(_components_AntiSpamSettingsPage__WEBPACK_IMPORTED_MODULE_1__["default"]).registerPermission({
    icon: 'fas fa-pastafarianism',
    label: flarum_admin_app__WEBPACK_IMPORTED_MODULE_0___default().translator.trans('fof-anti-spam.admin.permissions.spamblock_users_label'),
    permission: 'user.spamblock'
  }, 'moderate');
});

/***/ }),

/***/ "flarum/admin/app":
/*!**************************************************!*\
  !*** external "flarum.core.compat['admin/app']" ***!
  \**************************************************/
/***/ ((module) => {

"use strict";
module.exports = flarum.core.compat['admin/app'];

/***/ }),

/***/ "flarum/admin/components/ExtensionPage":
/*!***********************************************************************!*\
  !*** external "flarum.core.compat['admin/components/ExtensionPage']" ***!
  \***********************************************************************/
/***/ ((module) => {

"use strict";
module.exports = flarum.core.compat['admin/components/ExtensionPage'];

/***/ }),

/***/ "flarum/common/components/Link":
/*!***************************************************************!*\
  !*** external "flarum.core.compat['common/components/Link']" ***!
  \***************************************************************/
/***/ ((module) => {

"use strict";
module.exports = flarum.core.compat['common/components/Link'];

/***/ }),

/***/ "./node_modules/@babel/runtime/helpers/esm/inheritsLoose.js":
/*!******************************************************************!*\
  !*** ./node_modules/@babel/runtime/helpers/esm/inheritsLoose.js ***!
  \******************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ _inheritsLoose)
/* harmony export */ });
/* harmony import */ var _setPrototypeOf_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./setPrototypeOf.js */ "./node_modules/@babel/runtime/helpers/esm/setPrototypeOf.js");

function _inheritsLoose(subClass, superClass) {
  subClass.prototype = Object.create(superClass.prototype);
  subClass.prototype.constructor = subClass;
  (0,_setPrototypeOf_js__WEBPACK_IMPORTED_MODULE_0__["default"])(subClass, superClass);
}

/***/ }),

/***/ "./node_modules/@babel/runtime/helpers/esm/setPrototypeOf.js":
/*!*******************************************************************!*\
  !*** ./node_modules/@babel/runtime/helpers/esm/setPrototypeOf.js ***!
  \*******************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ _setPrototypeOf)
/* harmony export */ });
function _setPrototypeOf(o, p) {
  _setPrototypeOf = Object.setPrototypeOf ? Object.setPrototypeOf.bind() : function _setPrototypeOf(o, p) {
    o.__proto__ = p;
    return o;
  };
  return _setPrototypeOf(o, p);
}

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	(() => {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = (module) => {
/******/ 			var getter = module && module.__esModule ?
/******/ 				() => (module['default']) :
/******/ 				() => (module);
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry need to be wrapped in an IIFE because it need to be in strict mode.
(() => {
"use strict";
/*!******************!*\
  !*** ./admin.ts ***!
  \******************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _src_admin__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./src/admin */ "./src/admin/index.ts");

})();

module.exports = __webpack_exports__;
/******/ })()
;
//# sourceMappingURL=admin.js.map