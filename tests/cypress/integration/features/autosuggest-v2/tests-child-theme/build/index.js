/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./src/index.js":
/*!**********************!*\
  !*** ./src/index.js ***!
  \**********************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _style_css__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./style.css */ "./src/style.css");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__);


/**
 * Improved ElasticPress Autosuggest hooks implementation
 *
 * Available hooks:
 * - ep.Autosuggest.queryParams - Modify search query parameters
 * - ep.Autosuggest.suggestions - Filter search results
 * - ep.Autosuggest.suggestionItem - Customize individual suggestion item rendering
 * - ep.Autosuggest.suggestionList - Customize suggestion list rendering
 * - ep.Autosuggest.onItemClick - Triggered when a suggestion item is clicked
 * 
 * Available Events:
 * - ep_autosuggest_loaded - window.EPAutosuggest is available - safe to hook into ui overrides
 */

/**
 * Hook: ep.Autosuggest.queryParams
 * Example: Only show "post" post_types in the search results.
 * Status: Works
 */

const enableOnlyShowPosts = false;
if (enableOnlyShowPosts) {
  wp.hooks.addFilter("ep.Autosuggest.queryParams", "my-theme/filter-post-types", function (params, searchTerm, filters) {
    const newParams = new URLSearchParams(params.toString());
    newParams.set("post_type", "post");
    return newParams;
  });
}

/**
 * Hook: ep.Autosuggest.suggestions
 * Example: Only show posts with thumbnails
 * Status: Works
 */
const enableOnlyPostsWithThumbnails = false;
if (enableOnlyPostsWithThumbnails) {
  wp.hooks.addFilter("ep.Autosuggest.suggestions", "my-theme/only-with-thumbnails", function (searchResults, searchTerm) {
    return searchResults.filter(item => item._source.thumbnail && item._source.thumbnail !== "");
  });
}

/**
 * Hook: ep.Autosuggest.suggestions
 * Example: Add some extra data to the source object (could be output later with a suggestionItem hook)
 * Status: Works
 */
const enableEnhanceResults = false;
if (enableEnhanceResults) {
  wp.hooks.addFilter("ep.Autosuggest.suggestions", "my-theme/enhance-results", function (searchResults, searchTerm) {
    return searchResults.map(item => ({
      ...item,
      _source: {
        ...item._source,
        relevanceScore: 100,
        highlightedTitle: item._source.post_title
      }
    }));
  });
}

/**
 * Hook: ep.Autosuggest.onItemClick
 * Example: Console log data on click (could be used for GA tracking)
 * Status: Works
 */
const enableAddClickHook = false;
if (enableAddClickHook) {
  wp.hooks.addAction("ep.Autosuggest.onItemClick", "my-theme/track-clicks", function (suggestion, index, searchTerm) {
    console.log("on click:", suggestion, index, searchTerm);
  });
}

/**
 * Hook: ep.Autosuggest.suggestionItem
 * Example: Custom UI Overrides
 * Status: Works
 */
const registerCustomSuggestionItem = () => {
  const CustomSuggestionItem = props => {
    const {
      suggestion,
      isActive,
      onClick
    } = props;
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("li", {
      className: `custom-suggestion ${isActive ? "active" : ""}`,
      role: "option",
      "aria-selected": isActive,
      id: `suggestion-${suggestion.id}`,
      onMouseDown: onClick,
      tabIndex: -1,
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("a", {
        href: suggestion.url,
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsxs)("div", {
          className: "suggestion-content",
          children: [suggestion.thumbnail && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("img", {
            src: suggestion.thumbnail,
            alt: "",
            className: "thumb"
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsxs)("div", {
            className: "suggestion-text",
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsxs)("strong", {
              children: ["Title Here!!! : ", suggestion.title]
            }), suggestion.excerpt && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("p", {
              children: suggestion.excerpt
            }), suggestion.type && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("span", {
              className: "content-type",
              children: suggestion.type
            })]
          })]
        })
      })
    });
  };
  wp.hooks.addFilter("ep.Autosuggest.suggestionItem", "my-theme/custom-suggestion-item", function (props, originalSuggestion) {
    if (!originalSuggestion || !originalSuggestion._source) {
      return props;
    }
    return {
      ...props,
      renderSuggestion: () => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)(CustomSuggestionItem, {
        ...props
      })
    };
  }, 5);
};
document.addEventListener("ep_autosuggest_loaded", function () {
  registerCustomSuggestionItem();
});

/**
 * Hook: ep.Autosuggest.suggestionList
 * Example: Custom UI Overrides
 * Status: Works
 */
const registerCustomSuggestionList = () => {
  const CustomSuggestionList = props => {
    const {
      suggestions,
      activeIndex,
      onItemClick,
      SuggestionItemTemplate,
      showViewAll,
      onViewAll,
      expanded
    } = props;

    // Group suggestions by type
    const groupedSuggestions = {};
    suggestions.forEach(suggestion => {
      const type = suggestion.type || "other";
      if (!groupedSuggestions[type]) {
        groupedSuggestions[type] = [];
      }
      groupedSuggestions[type].push(suggestion);
    });
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsxs)("div", {
      className: "custom-suggestion-list",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("div", {
        className: "custom-header",
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsxs)("h3", {
          children: ["Search Results (", suggestions.length, ")"]
        })
      }), Object.entries(groupedSuggestions).map(([type, items]) => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsxs)("div", {
        className: "suggestion-group",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsxs)("h4", {
          className: "group-title",
          children: [type.charAt(0).toUpperCase() + type.slice(1), "s"]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("ul", {
          className: "group-items",
          role: "listbox",
          children: items.map((suggestion, idx) => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)(SuggestionItemTemplate, {
            suggestion: suggestion,
            isActive: suggestions.indexOf(suggestion) === activeIndex,
            onClick: () => onItemClick(suggestions.indexOf(suggestion))
          }, suggestion.id))
        })]
      }, type)), showViewAll && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("button", {
        className: "custom-view-all",
        onClick: onViewAll,
        type: "button",
        children: expanded ? "Show Less" : "Show All Results"
      })]
    });
  };
  wp.hooks.addFilter("ep.Autosuggest.suggestionList", "my-theme/custom-suggestion-list", function (props) {
    return {
      ...props,
      renderSuggestionList: () => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)(CustomSuggestionList, {
        ...props
      })
    };
  }, 5);
};
document.addEventListener("ep_autosuggest_loaded", function () {
  registerCustomSuggestionList();
});

/***/ }),

/***/ "./src/style.css":
/*!***********************!*\
  !*** ./src/style.css ***!
  \***********************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "react/jsx-runtime":
/*!**********************************!*\
  !*** external "ReactJSXRuntime" ***!
  \**********************************/
/***/ ((module) => {

module.exports = window["ReactJSXRuntime"];

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
/******/ 	// expose the modules object (__webpack_modules__)
/******/ 	__webpack_require__.m = __webpack_modules__;
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/chunk loaded */
/******/ 	(() => {
/******/ 		var deferred = [];
/******/ 		__webpack_require__.O = (result, chunkIds, fn, priority) => {
/******/ 			if(chunkIds) {
/******/ 				priority = priority || 0;
/******/ 				for(var i = deferred.length; i > 0 && deferred[i - 1][2] > priority; i--) deferred[i] = deferred[i - 1];
/******/ 				deferred[i] = [chunkIds, fn, priority];
/******/ 				return;
/******/ 			}
/******/ 			var notFulfilled = Infinity;
/******/ 			for (var i = 0; i < deferred.length; i++) {
/******/ 				var [chunkIds, fn, priority] = deferred[i];
/******/ 				var fulfilled = true;
/******/ 				for (var j = 0; j < chunkIds.length; j++) {
/******/ 					if ((priority & 1 === 0 || notFulfilled >= priority) && Object.keys(__webpack_require__.O).every((key) => (__webpack_require__.O[key](chunkIds[j])))) {
/******/ 						chunkIds.splice(j--, 1);
/******/ 					} else {
/******/ 						fulfilled = false;
/******/ 						if(priority < notFulfilled) notFulfilled = priority;
/******/ 					}
/******/ 				}
/******/ 				if(fulfilled) {
/******/ 					deferred.splice(i--, 1)
/******/ 					var r = fn();
/******/ 					if (r !== undefined) result = r;
/******/ 				}
/******/ 			}
/******/ 			return result;
/******/ 		};
/******/ 	})();
/******/ 	
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
/******/ 	/* webpack/runtime/jsonp chunk loading */
/******/ 	(() => {
/******/ 		// no baseURI
/******/ 		
/******/ 		// object to store loaded and loading chunks
/******/ 		// undefined = chunk not loaded, null = chunk preloaded/prefetched
/******/ 		// [resolve, reject, Promise] = chunk loading, 0 = chunk loaded
/******/ 		var installedChunks = {
/******/ 			"index": 0,
/******/ 			"./style-index": 0
/******/ 		};
/******/ 		
/******/ 		// no chunk on demand loading
/******/ 		
/******/ 		// no prefetching
/******/ 		
/******/ 		// no preloaded
/******/ 		
/******/ 		// no HMR
/******/ 		
/******/ 		// no HMR manifest
/******/ 		
/******/ 		__webpack_require__.O.j = (chunkId) => (installedChunks[chunkId] === 0);
/******/ 		
/******/ 		// install a JSONP callback for chunk loading
/******/ 		var webpackJsonpCallback = (parentChunkLoadingFunction, data) => {
/******/ 			var [chunkIds, moreModules, runtime] = data;
/******/ 			// add "moreModules" to the modules object,
/******/ 			// then flag all "chunkIds" as loaded and fire callback
/******/ 			var moduleId, chunkId, i = 0;
/******/ 			if(chunkIds.some((id) => (installedChunks[id] !== 0))) {
/******/ 				for(moduleId in moreModules) {
/******/ 					if(__webpack_require__.o(moreModules, moduleId)) {
/******/ 						__webpack_require__.m[moduleId] = moreModules[moduleId];
/******/ 					}
/******/ 				}
/******/ 				if(runtime) var result = runtime(__webpack_require__);
/******/ 			}
/******/ 			if(parentChunkLoadingFunction) parentChunkLoadingFunction(data);
/******/ 			for(;i < chunkIds.length; i++) {
/******/ 				chunkId = chunkIds[i];
/******/ 				if(__webpack_require__.o(installedChunks, chunkId) && installedChunks[chunkId]) {
/******/ 					installedChunks[chunkId][0]();
/******/ 				}
/******/ 				installedChunks[chunkId] = 0;
/******/ 			}
/******/ 			return __webpack_require__.O(result);
/******/ 		}
/******/ 		
/******/ 		var chunkLoadingGlobal = globalThis["webpackChunkchild_theme"] = globalThis["webpackChunkchild_theme"] || [];
/******/ 		chunkLoadingGlobal.forEach(webpackJsonpCallback.bind(null, 0));
/******/ 		chunkLoadingGlobal.push = webpackJsonpCallback.bind(null, chunkLoadingGlobal.push.bind(chunkLoadingGlobal));
/******/ 	})();
/******/ 	
/************************************************************************/
/******/ 	
/******/ 	// startup
/******/ 	// Load entry module and return exports
/******/ 	// This entry module depends on other loaded chunks and execution need to be delayed
/******/ 	var __webpack_exports__ = __webpack_require__.O(undefined, ["./style-index"], () => (__webpack_require__("./src/index.js")))
/******/ 	__webpack_exports__ = __webpack_require__.O(__webpack_exports__);
/******/ 	
/******/ })()
;
//# sourceMappingURL=index.js.map