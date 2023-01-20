(() => {
  function t(e) {
    return t = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (t) {
      return typeof t
    } : function (t) {
      return t && "function" == typeof Symbol && t.constructor === Symbol && t !== Symbol.prototype ? "symbol" : typeof t
    }, t(e)
  }

  function e(t, e) {
    if (!(t instanceof e)) throw new TypeError("Cannot call a class as a function")
  }

  function n(t, e) {
    for (var n = 0; n < e.length; n++) {
      var r = e[n];
      r.enumerable = r.enumerable || !1, r.configurable = !0, "value" in r && (r.writable = !0), Object.defineProperty(t, r.key, r)
    }
  }

  function r(e, n) {
    if (n && ("object" === t(n) || "function" == typeof n)) return n;
    if (void 0 !== n) throw new TypeError("Derived constructors may only return object or undefined");
    return function (t) {
      if (void 0 === t) throw new ReferenceError("this hasn't been initialised - super() hasn't been called");
      return t
    }(e)
  }

  function o(t) {
    var e = "function" == typeof Map ? new Map : void 0;
    return o = function (t) {
      if (null === t || (n = t, -1 === Function.toString.call(n).indexOf("[native code]"))) return t;
      var n;
      if ("function" != typeof t) throw new TypeError("Super expression must either be null or a function");
      if (void 0 !== e) {
        if (e.has(t)) return e.get(t);
        e.set(t, r)
      }

      function r() {
        return i(t, arguments, a(this).constructor)
      }

      return r.prototype = Object.create(t.prototype, {
        constructor: {
          value: r,
          enumerable: !1,
          writable: !0,
          configurable: !0
        }
      }), c(r, t)
    }, o(t)
  }

  function i(t, e, n) {
    return i = u() ? Reflect.construct : function (t, e, n) {
      var r = [null];
      r.push.apply(r, e);
      var o = new (Function.bind.apply(t, r));
      return n && c(o, n.prototype), o
    }, i.apply(null, arguments)
  }

  function u() {
    if ("undefined" == typeof Reflect || !Reflect.construct) return !1;
    if (Reflect.construct.sham) return !1;
    if ("function" == typeof Proxy) return !0;
    try {
      return Boolean.prototype.valueOf.call(Reflect.construct(Boolean, [], (function () {
      }))), !0
    } catch (t) {
      return !1
    }
  }

  function c(t, e) {
    return c = Object.setPrototypeOf || function (t, e) {
      return t.__proto__ = e, t
    }, c(t, e)
  }

  function a(t) {
    return a = Object.setPrototypeOf ? Object.getPrototypeOf : function (t) {
      return t.__proto__ || Object.getPrototypeOf(t)
    }, a(t)
  }

  var l = function (t) {
    !function (t, e) {
      if ("function" != typeof e && null !== e) throw new TypeError("Super expression must either be null or a function");
      t.prototype = Object.create(e && e.prototype, {
        constructor: {
          value: t,
          writable: !0,
          configurable: !0
        }
      }), e && c(t, e)
    }(d, t);
    var o, i, l, s, f, p = (o = d, i = u(), function () {
      var t, e = a(o);
      if (i) {
        var n = a(this).constructor;
        t = Reflect.construct(e, arguments, n)
      } else t = e.apply(this, arguments);
      return r(this, t)
    });

    function d() {
      return e(this, d), p.apply(this, arguments)
    }

    return l = d, f = [{
      key: "observedAttributes", get: function () {
        return ["title", "description", "retryButtonText"]
      }
    }], (s = [{
      key: "connectedCallback", value: function () {
        this.build()
      }
    }, {
      key: "attributeChangedCallback", value: function () {
        this.firstElementChild && (this.removeChild(this.firstElementChild), this.build())
      }
    }, {
      key: "build", value: function () {
        var t = this.createAlertDetails(), e = this.createCardContent();
        t.appendChild(e), this.appendChild(t)
      }
    }, {
      key: "createAlertDetails", value: function () {
        var t = document.createElement("div");
        return t.classList.add("mp-alert-details-card"), t
      }
    }, {
      key: "createCardContent", value: function () {
        var t = document.createElement("div");
        t.classList.add("mp-alert-details-card-content");
        var e = document.createElement("div");
        e.classList.add("mp-alert-details-card-content-left");
        var n = document.createElement("div");
        n.classList.add("mp-alert-details-card-content-right"), t.appendChild(e), t.appendChild(n);
        var r = this.createBadge(), o = this.createTitle(), i = this.createDescription(), u = this.createRetryButton();
        return e.appendChild(r), n.appendChild(o), n.appendChild(i), n.appendChild(u), t
      }
    }, {
      key: "createBadge", value: function () {
        var t = document.createElement("div");
        return t.innerHTML = "!", t.classList.add("mp-alert-details-badge"), t
      }
    }, {
      key: "createTitle", value: function () {
        var t = document.createElement("p");
        return t.innerHTML = this.getAttribute("title"), t.classList.add("mp-alert-details-title"), t
      }
    }, {
      key: "createDescription", value: function () {
        var t = document.createElement("p");
        return t.innerHTML = this.getAttribute("description"), t.classList.add("mp-alert-details-description"), t
      }
    }, {
      key: "createRetryButton", value: function () {
        var t = this.getAttribute("retryButtonText"), e = document.createElement("button");
        return e.classList.add("mp-alert-details-retry-button"), e.innerHTML = t, e.onclick = function () {
          return document.location.reload()
        }, e
      }
    }]) && n(l.prototype, s), f && n(l, f), d
  }(o(HTMLElement));
  customElements.define("alert-details", l)
})(), (() => {
  function t(e) {
    return t = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (t) {
      return typeof t
    } : function (t) {
      return t && "function" == typeof Symbol && t.constructor === Symbol && t !== Symbol.prototype ? "symbol" : typeof t
    }, t(e)
  }

  function e(t, e) {
    if (!(t instanceof e)) throw new TypeError("Cannot call a class as a function")
  }

  function n(t, e) {
    for (var n = 0; n < e.length; n++) {
      var r = e[n];
      r.enumerable = r.enumerable || !1, r.configurable = !0, "value" in r && (r.writable = !0), Object.defineProperty(t, r.key, r)
    }
  }

  function r(e, n) {
    if (n && ("object" === t(n) || "function" == typeof n)) return n;
    if (void 0 !== n) throw new TypeError("Derived constructors may only return object or undefined");
    return function (t) {
      if (void 0 === t) throw new ReferenceError("this hasn't been initialised - super() hasn't been called");
      return t
    }(e)
  }

  function o(t) {
    var e = "function" == typeof Map ? new Map : void 0;
    return o = function (t) {
      if (null === t || (n = t, -1 === Function.toString.call(n).indexOf("[native code]"))) return t;
      var n;
      if ("function" != typeof t) throw new TypeError("Super expression must either be null or a function");
      if (void 0 !== e) {
        if (e.has(t)) return e.get(t);
        e.set(t, r)
      }

      function r() {
        return i(t, arguments, a(this).constructor)
      }

      return r.prototype = Object.create(t.prototype, {
        constructor: {
          value: r,
          enumerable: !1,
          writable: !0,
          configurable: !0
        }
      }), c(r, t)
    }, o(t)
  }

  function i(t, e, n) {
    return i = u() ? Reflect.construct : function (t, e, n) {
      var r = [null];
      r.push.apply(r, e);
      var o = new (Function.bind.apply(t, r));
      return n && c(o, n.prototype), o
    }, i.apply(null, arguments)
  }

  function u() {
    if ("undefined" == typeof Reflect || !Reflect.construct) return !1;
    if (Reflect.construct.sham) return !1;
    if ("function" == typeof Proxy) return !0;
    try {
      return Boolean.prototype.valueOf.call(Reflect.construct(Boolean, [], (function () {
      }))), !0
    } catch (t) {
      return !1
    }
  }

  function c(t, e) {
    return c = Object.setPrototypeOf || function (t, e) {
      return t.__proto__ = e, t
    }, c(t, e)
  }

  function a(t) {
    return a = Object.setPrototypeOf ? Object.getPrototypeOf : function (t) {
      return t.__proto__ || Object.getPrototypeOf(t)
    }, a(t)
  }

  var l = function (o) {
    !function (t, e) {
      if ("function" != typeof e && null !== e) throw new TypeError("Super expression must either be null or a function");
      t.prototype = Object.create(e && e.prototype, {
        constructor: {
          value: t,
          writable: !0,
          configurable: !0
        }
      }), e && c(t, e)
    }(d, o);
    var i, l, s, f, p = (i = d, l = u(), function () {
      var t, e = a(i);
      if (l) {
        var n = a(this).constructor;
        t = Reflect.construct(e, arguments, n)
      } else t = e.apply(this, arguments);
      return r(this, t)
    });

    function d() {
      return e(this, d), p.apply(this, arguments)
    }

    return s = d, (f = [{
      key: "connectedCallback", value: function () {
        this.build()
      }
    }, {
      key: "build", value: function () {
        this.appendChild(this.createContainer())
      }
    }, {
      key: "createContainer", value: function () {
        var t = document.createElement("div");
        return t.classList.add("mp-checkout-benefits-container"), t.appendChild(this.createTitle()), t.appendChild(this.createList()), t
      }
    }, {
      key: "createTitle", value: function () {
        var t = document.createElement("p");
        t.classList.add("mp-checkout-benefits-title"), t.innerHTML = this.getAttribute("title");
        var e = this.getAttribute("title-align");
        return "center" === e && t.style.setProperty("text-align", "center", "important"), "left" === e && t.style.setProperty("text-align", "left", "important"), t
      }
    }, {
      key: "createList", value: function () {
        var t = this, e = JSON.parse(this.getAttribute("items")), n = document.createElement("div");
        return n.classList.add("mp-checkout-benefits-list"), e.forEach((function (e, r) {
          n.appendChild(t.createItem(e, r))
        })), n
      }
    }, {
      key: "createItem", value: function (e, n) {
        var r = document.createElement("div");
        r.classList.add("mp-checkout-benefits-item");
        var o = this.getAttribute("list-mode");
        return "count" === o ? (r.appendChild(this.createCountList(n)), r.appendChild(this.createSimpleText(e)), r) : "bullet" === o ? (r.appendChild(this.createBulletList()), r.appendChild(this.createSimpleText(e)), r) : "image" === o && "object" === t(e) ? (r.appendChild(this.createImageList(e)), r.appendChild(this.createCompositeText(e)), r) : r
      }
    }, {
      key: "createSimpleText", value: function (t) {
        var e = document.createElement("span");
        return e.innerHTML = t, e
      }
    }, {
      key: "createCompositeText", value: function (t) {
        var e = t.title, n = t.subtitle, r = document.createElement("p");
        r.classList.add("mp-checkout-benefits-item-title"), r.innerHTML = e;
        var o = document.createElement("p");
        o.classList.add("mp-checkout-benefits-item-subtitle"), o.innerHTML = n;
        var i = document.createElement("span");
        return i.appendChild(r), i.appendChild(o), i
      }
    }, {
      key: "createCountList", value: function (t) {
        var e = document.createElement("p");
        e.innerText = 1 + t, e.classList.add("mp-checkout-benefits-count-list-item");
        var n = document.createElement("div");
        return n.classList.add("mp-checkout-benefits-count-list-div"), n.appendChild(e), n
      }
    }, {
      key: "createBulletList", value: function () {
        var t = document.createElement("div");
        t.classList.add("mp-checkout-benefits-tick-mark-container");
        var e = document.createElement("div");
        return e.classList.add("mp-checkout-benefits-tick-mark"), t.appendChild(e), t
      }
    }, {
      key: "createImageList", value: function (t) {
        var e = t.image, n = document.createElement("div");
        return n.classList.add("mp-checkout-benefits-image-list"), n.appendChild(this.createImage(e)), n
      }
    }, {
      key: "createImage", value: function (t) {
        var e = t.src, n = t.alt, r = document.createElement("img");
        return r.classList.add("mp-checkout-benefits-image"), r.setAttribute("src", e), r.setAttribute("alt", n), r
      }
    }]) && n(s.prototype, f), d
  }(o(HTMLElement));
  customElements.define("checkout-benefits", l)
})(), (() => {
  function t(e) {
    return t = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (t) {
      return typeof t
    } : function (t) {
      return t && "function" == typeof Symbol && t.constructor === Symbol && t !== Symbol.prototype ? "symbol" : typeof t
    }, t(e)
  }

  function e(t, e) {
    if (!(t instanceof e)) throw new TypeError("Cannot call a class as a function")
  }

  function n(t, e) {
    for (var n = 0; n < e.length; n++) {
      var r = e[n];
      r.enumerable = r.enumerable || !1, r.configurable = !0, "value" in r && (r.writable = !0), Object.defineProperty(t, r.key, r)
    }
  }

  function r(e, n) {
    if (n && ("object" === t(n) || "function" == typeof n)) return n;
    if (void 0 !== n) throw new TypeError("Derived constructors may only return object or undefined");
    return function (t) {
      if (void 0 === t) throw new ReferenceError("this hasn't been initialised - super() hasn't been called");
      return t
    }(e)
  }

  function o(t) {
    var e = "function" == typeof Map ? new Map : void 0;
    return o = function (t) {
      if (null === t || (n = t, -1 === Function.toString.call(n).indexOf("[native code]"))) return t;
      var n;
      if ("function" != typeof t) throw new TypeError("Super expression must either be null or a function");
      if (void 0 !== e) {
        if (e.has(t)) return e.get(t);
        e.set(t, r)
      }

      function r() {
        return i(t, arguments, a(this).constructor)
      }

      return r.prototype = Object.create(t.prototype, {
        constructor: {
          value: r,
          enumerable: !1,
          writable: !0,
          configurable: !0
        }
      }), c(r, t)
    }, o(t)
  }

  function i(t, e, n) {
    return i = u() ? Reflect.construct : function (t, e, n) {
      var r = [null];
      r.push.apply(r, e);
      var o = new (Function.bind.apply(t, r));
      return n && c(o, n.prototype), o
    }, i.apply(null, arguments)
  }

  function u() {
    if ("undefined" == typeof Reflect || !Reflect.construct) return !1;
    if (Reflect.construct.sham) return !1;
    if ("function" == typeof Proxy) return !0;
    try {
      return Boolean.prototype.valueOf.call(Reflect.construct(Boolean, [], (function () {
      }))), !0
    } catch (t) {
      return !1
    }
  }

  function c(t, e) {
    return c = Object.setPrototypeOf || function (t, e) {
      return t.__proto__ = e, t
    }, c(t, e)
  }

  function a(t) {
    return a = Object.setPrototypeOf ? Object.getPrototypeOf : function (t) {
      return t.__proto__ || Object.getPrototypeOf(t)
    }, a(t)
  }

  var l = function (t) {
    !function (t, e) {
      if ("function" != typeof e && null !== e) throw new TypeError("Super expression must either be null or a function");
      t.prototype = Object.create(e && e.prototype, {
        constructor: {
          value: t,
          writable: !0,
          configurable: !0
        }
      }), e && c(t, e)
    }(p, t);
    var o, i, l, s, f = (o = p, i = u(), function () {
      var t, e = a(o);
      if (i) {
        var n = a(this).constructor;
        t = Reflect.construct(e, arguments, n)
      } else t = e.apply(this, arguments);
      return r(this, t)
    });

    function p() {
      return e(this, p), f.apply(this, arguments)
    }

    return l = p, (s = [{
      key: "connectedCallback", value: function () {
        this.build()
      }
    }, {
      key: "build", value: function () {
        this.appendChild(this.createContainer())
      }
    }, {
      key: "createContainer", value: function () {
        var t = document.createElement("div");
        return t.classList.add("mp-checkout-redirect-v2-container"), t.setAttribute("data-cy", "checkout-redirect-v2-container"), t.appendChild(this.createImage()), t.appendChild(this.createText()), t
      }
    }, {
      key: "createImage", value: function () {
        var t = document.createElement("img");
        return t.classList.add("mp-checkout-redirect-v2-image"), t.src = this.getAttribute("src"), t.alt = this.getAttribute("alt"), t
      }
    }, {
      key: "createText", value: function () {
        var t = document.createElement("p");
        return t.classList.add("mp-checkout-redirect-v2-text"), t.innerHTML = this.getAttribute("text"), t
      }
    }]) && n(l.prototype, s), p
  }(o(HTMLElement));
  customElements.define("checkout-redirect-v2", l)
})(), (() => {
  function t(e) {
    return t = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (t) {
      return typeof t
    } : function (t) {
      return t && "function" == typeof Symbol && t.constructor === Symbol && t !== Symbol.prototype ? "symbol" : typeof t
    }, t(e)
  }

  function e(t, e) {
    if (!(t instanceof e)) throw new TypeError("Cannot call a class as a function")
  }

  function n(t, e) {
    for (var n = 0; n < e.length; n++) {
      var r = e[n];
      r.enumerable = r.enumerable || !1, r.configurable = !0, "value" in r && (r.writable = !0), Object.defineProperty(t, r.key, r)
    }
  }

  function r(e, n) {
    if (n && ("object" === t(n) || "function" == typeof n)) return n;
    if (void 0 !== n) throw new TypeError("Derived constructors may only return object or undefined");
    return function (t) {
      if (void 0 === t) throw new ReferenceError("this hasn't been initialised - super() hasn't been called");
      return t
    }(e)
  }

  function o(t) {
    var e = "function" == typeof Map ? new Map : void 0;
    return o = function (t) {
      if (null === t || (n = t, -1 === Function.toString.call(n).indexOf("[native code]"))) return t;
      var n;
      if ("function" != typeof t) throw new TypeError("Super expression must either be null or a function");
      if (void 0 !== e) {
        if (e.has(t)) return e.get(t);
        e.set(t, r)
      }

      function r() {
        return i(t, arguments, a(this).constructor)
      }

      return r.prototype = Object.create(t.prototype, {
        constructor: {
          value: r,
          enumerable: !1,
          writable: !0,
          configurable: !0
        }
      }), c(r, t)
    }, o(t)
  }

  function i(t, e, n) {
    return i = u() ? Reflect.construct : function (t, e, n) {
      var r = [null];
      r.push.apply(r, e);
      var o = new (Function.bind.apply(t, r));
      return n && c(o, n.prototype), o
    }, i.apply(null, arguments)
  }

  function u() {
    if ("undefined" == typeof Reflect || !Reflect.construct) return !1;
    if (Reflect.construct.sham) return !1;
    if ("function" == typeof Proxy) return !0;
    try {
      return Boolean.prototype.valueOf.call(Reflect.construct(Boolean, [], (function () {
      }))), !0
    } catch (t) {
      return !1
    }
  }

  function c(t, e) {
    return c = Object.setPrototypeOf || function (t, e) {
      return t.__proto__ = e, t
    }, c(t, e)
  }

  function a(t) {
    return a = Object.setPrototypeOf ? Object.getPrototypeOf : function (t) {
      return t.__proto__ || Object.getPrototypeOf(t)
    }, a(t)
  }

  var l = function (t) {
    !function (t, e) {
      if ("function" != typeof e && null !== e) throw new TypeError("Super expression must either be null or a function");
      t.prototype = Object.create(e && e.prototype, {
        constructor: {
          value: t,
          writable: !0,
          configurable: !0
        }
      }), e && c(t, e)
    }(p, t);
    var o, i, l, s, f = (o = p, i = u(), function () {
      var t, e = a(o);
      if (i) {
        var n = a(this).constructor;
        t = Reflect.construct(e, arguments, n)
      } else t = e.apply(this, arguments);
      return r(this, t)
    });

    function p() {
      return e(this, p), f.apply(this, arguments)
    }

    return l = p, (s = [{
      key: "connectedCallback", value: function () {
        this.build()
      }
    }, {
      key: "build", value: function () {
        this.appendChild(this.createContainer())
      }
    }, {
      key: "createContainer", value: function () {
        var t = document.createElement("div");
        return t.classList.add("mp-checkout-redirect-container"), t.setAttribute("data-cy", "checkout-redirect-container"), t.appendChild(this.createImage()), t.appendChild(this.createText()), t
      }
    }, {
      key: "createImage", value: function () {
        var t = document.createElement("img");
        return t.classList.add("mp-checkout-redirect-image"), t.src = this.getAttribute("src"), t.alt = this.getAttribute("alt"), t
      }
    }, {
      key: "createText", value: function () {
        var t = document.createElement("p");
        return t.classList.add("mp-checkout-redirect-text"), t.innerHTML = this.getAttribute("text"), t
      }
    }]) && n(l.prototype, s), p
  }(o(HTMLElement));
  customElements.define("checkout-redirect", l)
})(), (() => {
  function t(e) {
    return t = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (t) {
      return typeof t
    } : function (t) {
      return t && "function" == typeof Symbol && t.constructor === Symbol && t !== Symbol.prototype ? "symbol" : typeof t
    }, t(e)
  }

  function e(t, e) {
    if (!(t instanceof e)) throw new TypeError("Cannot call a class as a function")
  }

  function n(t, e) {
    for (var n = 0; n < e.length; n++) {
      var r = e[n];
      r.enumerable = r.enumerable || !1, r.configurable = !0, "value" in r && (r.writable = !0), Object.defineProperty(t, r.key, r)
    }
  }

  function r(e, n) {
    if (n && ("object" === t(n) || "function" == typeof n)) return n;
    if (void 0 !== n) throw new TypeError("Derived constructors may only return object or undefined");
    return function (t) {
      if (void 0 === t) throw new ReferenceError("this hasn't been initialised - super() hasn't been called");
      return t
    }(e)
  }

  function o(t) {
    var e = "function" == typeof Map ? new Map : void 0;
    return o = function (t) {
      if (null === t || (n = t, -1 === Function.toString.call(n).indexOf("[native code]"))) return t;
      var n;
      if ("function" != typeof t) throw new TypeError("Super expression must either be null or a function");
      if (void 0 !== e) {
        if (e.has(t)) return e.get(t);
        e.set(t, r)
      }

      function r() {
        return i(t, arguments, a(this).constructor)
      }

      return r.prototype = Object.create(t.prototype, {
        constructor: {
          value: r,
          enumerable: !1,
          writable: !0,
          configurable: !0
        }
      }), c(r, t)
    }, o(t)
  }

  function i(t, e, n) {
    return i = u() ? Reflect.construct : function (t, e, n) {
      var r = [null];
      r.push.apply(r, e);
      var o = new (Function.bind.apply(t, r));
      return n && c(o, n.prototype), o
    }, i.apply(null, arguments)
  }

  function u() {
    if ("undefined" == typeof Reflect || !Reflect.construct) return !1;
    if (Reflect.construct.sham) return !1;
    if ("function" == typeof Proxy) return !0;
    try {
      return Boolean.prototype.valueOf.call(Reflect.construct(Boolean, [], (function () {
      }))), !0
    } catch (t) {
      return !1
    }
  }

  function c(t, e) {
    return c = Object.setPrototypeOf || function (t, e) {
      return t.__proto__ = e, t
    }, c(t, e)
  }

  function a(t) {
    return a = Object.setPrototypeOf ? Object.getPrototypeOf : function (t) {
      return t.__proto__ || Object.getPrototypeOf(t)
    }, a(t)
  }

  var l = function (t) {
    !function (t, e) {
      if ("function" != typeof e && null !== e) throw new TypeError("Super expression must either be null or a function");
      t.prototype = Object.create(e && e.prototype, {
        constructor: {
          value: t,
          writable: !0,
          configurable: !0
        }
      }), e && c(t, e)
    }(p, t);
    var o, i, l, s, f = (o = p, i = u(), function () {
      var t, e = a(o);
      if (i) {
        var n = a(this).constructor;
        t = Reflect.construct(e, arguments, n)
      } else t = e.apply(this, arguments);
      return r(this, t)
    });

    function p() {
      return e(this, p), f.apply(this, arguments)
    }

    return l = p, (s = [{
      key: "connectedCallback", value: function () {
        this.build()
      }
    }, {
      key: "build", value: function () {
        var t = this.createInputDocument();
        this.appendChild(t)
      }
    }, {
      key: "createInputDocument", value: function () {
        var t = document.createElement("div");
        t.classList.add("mp-input-document"), t.setAttribute("data-cy", "input-document-container");
        var e = this.createLabel(this.getAttribute("label-message")),
          n = this.createHelper(this.getAttribute("helper-message")),
          r = this.createHiddenField(this.getAttribute("hidden-id")), o = this.createInput(n, r);
        return t.appendChild(e), t.appendChild(o), t.appendChild(r), t.appendChild(n), t
      }
    }, {
      key: "createLabel", value: function (t) {
        var e = document.createElement("input-label");
        return e.setAttribute("message", t), e.setAttribute("isOptional", "false"), e
      }
    }, {
      key: "createInput", value: function (t, e) {
        var n = this, r = document.createElement("div");
        r.classList.add("mp-input"), r.setAttribute("id", "form-checkout__identificationNumber-container");
        var o = JSON.parse(this.getAttribute("documents")), i = this.getAttribute("validate"),
          u = this.createVerticalLine(), c = this.createSelect(r, t, o, i), a = this.createDocument(r, c, t);
        return c.addEventListener("change", (function () {
          r.classList.remove("mp-focus"), r.classList.remove("mp-error"), n.setInpuProperties(c, a), n.setMaskInputDocument(c, a, e)
        })), r.appendChild(c), r.appendChild(u), r.appendChild(a), this.setMaskInputDocument(c, a, e), r
      }
    }, {
      key: "setInpuProperties", value: function (t, e) {
        "CPF" === t.value ? (e.value = "", e.setAttribute("maxlength", "14"), e.setAttribute("placeholder", "999.999.999-99")) : "CNPJ" === t.value ? (e.value = "", e.setAttribute("maxlength", "18"), e.setAttribute("placeholder", "99.999.999/0001-99")) : "CI" === t.value ? (e.value = "", e.setAttribute("maxlength", "8"), e.setAttribute("placeholder", "99999999")) : (e.value = "", e.setAttribute("maxlength", "20"), e.setAttribute("placeholder", ""))
      }
    }, {
      key: "createSelect", value: function (t, e, n, r) {
        var o = this, i = document.createElement("select");
        return i.classList.add("mp-document-select"), i.setAttribute("name", this.getAttribute("select-name")), i.setAttribute("id", this.getAttribute("select-id")), i.setAttribute("data-checkout", this.getAttribute("select-data-checkout")), i.setAttribute("data-cy", "select-document"), n && n.forEach((function (t) {
          o.createOption(i, t)
        })), r && (i.addEventListener("focus", (function () {
          t.classList.add("mp-focus"), e.firstElementChild.style.display = "none"
        })), i.addEventListener("focusout", (function () {
          t.classList.remove("mp-focus"), e.firstElementChild.style.display = "none"
        }))), i
      }
    }, {
      key: "createOption", value: function (t, e) {
        var n = document.createElement("option");
        n.innerHTML = e, n.value = e, t.appendChild(n)
      }
    }, {
      key: "createHiddenField", value: function (t) {
        var e = document.createElement("input");
        return e.setAttribute("type", "hidden"), e.setAttribute("id", t), e
      }
    }, {
      key: "createVerticalLine", value: function () {
        var t = document.createElement("div");
        return t.classList.add("mp-vertical-line"), t
      }
    }, {
      key: "isValidCPF", value: function (t) {
        if ("string" != typeof t) return !1;
        if (!(t = t.replace(/[\s.-]*/gim, "")) || 11 !== t.length || "00000000000" === t || "11111111111" === t || "22222222222" === t || "33333333333" === t || "44444444444" === t || "55555555555" === t || "66666666666" === t || "77777777777" === t || "88888888888" === t || "99999999999" === t) return !1;
        for (var e, n = 0, r = 1; r <= 9; r += 1) n += parseInt(t.substring(r - 1, r)) * (11 - r);
        if (10 != (e = 10 * n % 11) && 11 !== e || (e = 0), e !== parseInt(t.substring(9, 10))) return !1;
        n = 0;
        for (var o = 1; o <= 10; o += 1) n += parseInt(t.substring(o - 1, o)) * (12 - o);
        return 10 != (e = 10 * n % 11) && 11 !== e || (e = 0), e === parseInt(t.substring(10, 11))
      }
    }, {
      key: "isValidCNPJ", value: function (t) {
        if ("" === (t = t.replace(/[^\d]+/g, ""))) return !1;
        if (14 !== t.length) return !1;
        if ("00000000000000" === t || "11111111111111" === t || "22222222222222" === t || "33333333333333" === t || "44444444444444" === t || "55555555555555" === t || "66666666666666" === t || "77777777777777" === t || "88888888888888" === t || "99999999999999" === t) return !1;
        for (var e = t.length - 2, n = t.substring(0, e), r = t.substring(e), o = 0, i = e - 7, u = e; u >= 1; u -= 1) o += n.charAt(e - u) * i--, i < 2 && (i = 9);
        var c = o % 11 < 2 ? 0 : 11 - o % 11;
        if (c !== Number(r.charAt(0))) return !1;
        e += 1, n = t.substring(0, e), o = 0, i = e - 7;
        for (var a = e; a >= 1; a -= 1) o += n.charAt(e - a) * i--, i < 2 && (i = 9);
        return (c = o % 11 < 2 ? 0 : 11 - o % 11) === Number(r.charAt(1))
      }
    }, {
      key: "isValidCI", value: function (t) {
        var e = 0, n = 0, r = t[t.length - 1];
        if (t.length <= 6) for (n = t.length; n < 7; n += 1) t = "0".concat(t);
        for (n = 0; n < 7; n += 1) e += parseInt("2987634"[n], 10) * parseInt(t[n], 10) % 10;
        return r === (e % 10 == 0 ? 0 : 10 - e % 10).toString()
      }
    }, {
      key: "setMaskInputDocument", value: function (t, e, n) {
        var r = {
          CPF: function (t) {
            return t.replace(/\D+/g, "").replace(/(\d{3})(\d)/, "$1.$2").replace(/(\d{3})(\d)/, "$1.$2").replace(/(\d{3})(\d{1,2})/, "$1-$2").replace(/(-\d{2})\d+?$/, "$1")
          }, CNPJ: function (t) {
            return t.replace(/\D+/g, "").replace(/(\d{2})(\d)/, "$1.$2").replace(/(\d{3})(\d)/, "$1.$2").replace(/(\d{3})(\d)/, "$1/$2").replace(/(\d{4})(\d)/, "$1-$2").replace(/(-\d{2})\d+?$/, "$1")
          }, CI: function (t) {
            return t.replace(/\D+/g, "")
          }
        };
        e.addEventListener("input", (function (e) {
          if (void 0 !== r[t.value] && (e.target.value = r[t.value](e.target.value)), n) {
            var o = e.target.value.replace(/\D/g, "");
            n.value = o
          }
        }))
      }
    }, {
      key: "createDocument", value: function (t, e, n) {
        var r = this, o = document.createElement("input");
        return o.setAttribute("name", this.getAttribute("input-name")), o.setAttribute("data-checkout", this.getAttribute("input-data-checkout")), o.setAttribute("data-cy", "input-document"), o.classList.add("mp-document"), o.type = "text", o.inputMode = "numeric", this.setInpuProperties(e, o), o.addEventListener("focus", (function () {
          t.classList.add("mp-focus"), t.classList.remove("mp-error"), n.firstElementChild.style.display = "none"
        })), o.addEventListener("focusout", (function () {
          t.classList.remove("mp-focus");
          var i = {
            CPF: function (t) {
              return r.isValidCPF(t)
            }, CNPJ: function (t) {
              return r.isValidCNPJ(t)
            }, CI: function (t) {
              return r.isValidCI(t)
            }
          };
          void 0 !== i[e.value] && (i[e.value](o.value) ? (t.classList.remove("mp-error"), n.firstElementChild.style.display = "none", o.setAttribute("name", r.getAttribute("input-name"))) : (t.classList.add("mp-error"), n.firstElementChild.style.display = "flex", o.setAttribute("name", r.getAttribute("flag-error"))))
        })), o
      }
    }, {
      key: "createHelper", value: function (t) {
        var e = document.createElement("input-helper");
        return e.setAttribute("isVisible", !1), e.setAttribute("message", t), e.setAttribute("input-id", "mp-doc-number-helper"), e
      }
    }]) && n(l.prototype, s), p
  }(o(HTMLElement));
  customElements.define("input-document", l)
})(), (() => {
  function t(e) {
    return t = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (t) {
      return typeof t
    } : function (t) {
      return t && "function" == typeof Symbol && t.constructor === Symbol && t !== Symbol.prototype ? "symbol" : typeof t
    }, t(e)
  }

  function e(t, e) {
    if (!(t instanceof e)) throw new TypeError("Cannot call a class as a function")
  }

  function n(t, e) {
    for (var n = 0; n < e.length; n++) {
      var r = e[n];
      r.enumerable = r.enumerable || !1, r.configurable = !0, "value" in r && (r.writable = !0), Object.defineProperty(t, r.key, r)
    }
  }

  function r(e, n) {
    if (n && ("object" === t(n) || "function" == typeof n)) return n;
    if (void 0 !== n) throw new TypeError("Derived constructors may only return object or undefined");
    return function (t) {
      if (void 0 === t) throw new ReferenceError("this hasn't been initialised - super() hasn't been called");
      return t
    }(e)
  }

  function o(t) {
    var e = "function" == typeof Map ? new Map : void 0;
    return o = function (t) {
      if (null === t || (n = t, -1 === Function.toString.call(n).indexOf("[native code]"))) return t;
      var n;
      if ("function" != typeof t) throw new TypeError("Super expression must either be null or a function");
      if (void 0 !== e) {
        if (e.has(t)) return e.get(t);
        e.set(t, r)
      }

      function r() {
        return i(t, arguments, a(this).constructor)
      }

      return r.prototype = Object.create(t.prototype, {
        constructor: {
          value: r,
          enumerable: !1,
          writable: !0,
          configurable: !0
        }
      }), c(r, t)
    }, o(t)
  }

  function i(t, e, n) {
    return i = u() ? Reflect.construct : function (t, e, n) {
      var r = [null];
      r.push.apply(r, e);
      var o = new (Function.bind.apply(t, r));
      return n && c(o, n.prototype), o
    }, i.apply(null, arguments)
  }

  function u() {
    if ("undefined" == typeof Reflect || !Reflect.construct) return !1;
    if (Reflect.construct.sham) return !1;
    if ("function" == typeof Proxy) return !0;
    try {
      return Boolean.prototype.valueOf.call(Reflect.construct(Boolean, [], (function () {
      }))), !0
    } catch (t) {
      return !1
    }
  }

  function c(t, e) {
    return c = Object.setPrototypeOf || function (t, e) {
      return t.__proto__ = e, t
    }, c(t, e)
  }

  function a(t) {
    return a = Object.setPrototypeOf ? Object.getPrototypeOf : function (t) {
      return t.__proto__ || Object.getPrototypeOf(t)
    }, a(t)
  }

  var l = function (t) {
    !function (t, e) {
      if ("function" != typeof e && null !== e) throw new TypeError("Super expression must either be null or a function");
      t.prototype = Object.create(e && e.prototype, {
        constructor: {
          value: t,
          writable: !0,
          configurable: !0
        }
      }), e && c(t, e)
    }(p, t);
    var o, i, l, s, f = (o = p, i = u(), function () {
      var t, e = a(o);
      if (i) {
        var n = a(this).constructor;
        t = Reflect.construct(e, arguments, n)
      } else t = e.apply(this, arguments);
      return r(this, t)
    });

    function p() {
      return e(this, p), f.apply(this, arguments)
    }

    return l = p, (s = [{
      key: "connectedCallback", value: function () {
        this.build()
      }
    }, {
      key: "build", value: function () {
        this.appendChild(this.createHelper())
      }
    }, {
      key: "createHelper", value: function () {
        var t = document.createElement("div");
        t.classList.add("mp-helper"), t.setAttribute("id", this.getAttribute("input-id")), t.setAttribute("data-cy", "helper-container"), this.validateVisibility(t);
        var e = this.createIcon(), n = this.getAttribute("message"), r = this.createHelperMessage(n);
        return t.appendChild(e), t.appendChild(r), t
      }
    }, {
      key: "createIcon", value: function () {
        var t = document.createElement("div");
        return t.innerHTML = "!", t.classList.add("mp-helper-icon"), t
      }
    }, {
      key: "createHelperMessage", value: function (t) {
        var e = document.createElement("div");
        return e.innerHTML = t, e.classList.add("mp-helper-message"), e.setAttribute("data-cy", "helper-message"), e
      }
    }, {
      key: "validateVisibility", value: function (t) {
        var e = this.getAttribute("isVisible");
        "string" == typeof e && (e = "false" !== e), t.style.display = e ? "flex" : "none"
      }
    }]) && n(l.prototype, s), p
  }(o(HTMLElement));
  customElements.define("input-helper", l)
})(), (() => {
  function t(e) {
    return t = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (t) {
      return typeof t
    } : function (t) {
      return t && "function" == typeof Symbol && t.constructor === Symbol && t !== Symbol.prototype ? "symbol" : typeof t
    }, t(e)
  }

  function e(t, e) {
    if (!(t instanceof e)) throw new TypeError("Cannot call a class as a function")
  }

  function n(t, e) {
    for (var n = 0; n < e.length; n++) {
      var r = e[n];
      r.enumerable = r.enumerable || !1, r.configurable = !0, "value" in r && (r.writable = !0), Object.defineProperty(t, r.key, r)
    }
  }

  function r(e, n) {
    if (n && ("object" === t(n) || "function" == typeof n)) return n;
    if (void 0 !== n) throw new TypeError("Derived constructors may only return object or undefined");
    return function (t) {
      if (void 0 === t) throw new ReferenceError("this hasn't been initialised - super() hasn't been called");
      return t
    }(e)
  }

  function o(t) {
    var e = "function" == typeof Map ? new Map : void 0;
    return o = function (t) {
      if (null === t || (n = t, -1 === Function.toString.call(n).indexOf("[native code]"))) return t;
      var n;
      if ("function" != typeof t) throw new TypeError("Super expression must either be null or a function");
      if (void 0 !== e) {
        if (e.has(t)) return e.get(t);
        e.set(t, r)
      }

      function r() {
        return i(t, arguments, a(this).constructor)
      }

      return r.prototype = Object.create(t.prototype, {
        constructor: {
          value: r,
          enumerable: !1,
          writable: !0,
          configurable: !0
        }
      }), c(r, t)
    }, o(t)
  }

  function i(t, e, n) {
    return i = u() ? Reflect.construct : function (t, e, n) {
      var r = [null];
      r.push.apply(r, e);
      var o = new (Function.bind.apply(t, r));
      return n && c(o, n.prototype), o
    }, i.apply(null, arguments)
  }

  function u() {
    if ("undefined" == typeof Reflect || !Reflect.construct) return !1;
    if (Reflect.construct.sham) return !1;
    if ("function" == typeof Proxy) return !0;
    try {
      return Boolean.prototype.valueOf.call(Reflect.construct(Boolean, [], (function () {
      }))), !0
    } catch (t) {
      return !1
    }
  }

  function c(t, e) {
    return c = Object.setPrototypeOf || function (t, e) {
      return t.__proto__ = e, t
    }, c(t, e)
  }

  function a(t) {
    return a = Object.setPrototypeOf ? Object.getPrototypeOf : function (t) {
      return t.__proto__ || Object.getPrototypeOf(t)
    }, a(t)
  }

  var l = function (t) {
    !function (t, e) {
      if ("function" != typeof e && null !== e) throw new TypeError("Super expression must either be null or a function");
      t.prototype = Object.create(e && e.prototype, {
        constructor: {
          value: t,
          writable: !0,
          configurable: !0
        }
      }), e && c(t, e)
    }(p, t);
    var o, i, l, s, f = (o = p, i = u(), function () {
      var t, e = a(o);
      if (i) {
        var n = a(this).constructor;
        t = Reflect.construct(e, arguments, n)
      } else t = e.apply(this, arguments);
      return r(this, t)
    });

    function p() {
      return e(this, p), f.apply(this, arguments)
    }

    return l = p, (s = [{
      key: "connectedCallback", value: function () {
        this.build()
      }
    }, {
      key: "build", value: function () {
        this.appendChild(this.createLabel())
      }
    }, {
      key: "createLabel", value: function () {
        var t = document.createElement("div");
        t.classList.add("mp-input-label"), t.setAttribute("data-cy", "input-label");
        var e = this.getAttribute("message");
        t.innerHTML = e;
        var n = this.getAttribute("isOptional");
        if ("string" == typeof n && (n = "false" !== n), !n) {
          var r = document.createElement("b");
          r.innerHTML = "*", r.style = "color: red", t.appendChild(r)
        }
        return t
      }
    }]) && n(l.prototype, s), p
  }(o(HTMLElement));
  customElements.define("input-label", l)
})(), (() => {
  function t(e) {
    return t = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (t) {
      return typeof t
    } : function (t) {
      return t && "function" == typeof Symbol && t.constructor === Symbol && t !== Symbol.prototype ? "symbol" : typeof t
    }, t(e)
  }

  function e(t, e) {
    if (!(t instanceof e)) throw new TypeError("Cannot call a class as a function")
  }

  function n(t, e) {
    for (var n = 0; n < e.length; n++) {
      var r = e[n];
      r.enumerable = r.enumerable || !1, r.configurable = !0, "value" in r && (r.writable = !0), Object.defineProperty(t, r.key, r)
    }
  }

  function r(e, n) {
    if (n && ("object" === t(n) || "function" == typeof n)) return n;
    if (void 0 !== n) throw new TypeError("Derived constructors may only return object or undefined");
    return function (t) {
      if (void 0 === t) throw new ReferenceError("this hasn't been initialised - super() hasn't been called");
      return t
    }(e)
  }

  function o(t) {
    var e = "function" == typeof Map ? new Map : void 0;
    return o = function (t) {
      if (null === t || (n = t, -1 === Function.toString.call(n).indexOf("[native code]"))) return t;
      var n;
      if ("function" != typeof t) throw new TypeError("Super expression must either be null or a function");
      if (void 0 !== e) {
        if (e.has(t)) return e.get(t);
        e.set(t, r)
      }

      function r() {
        return i(t, arguments, a(this).constructor)
      }

      return r.prototype = Object.create(t.prototype, {
        constructor: {
          value: r,
          enumerable: !1,
          writable: !0,
          configurable: !0
        }
      }), c(r, t)
    }, o(t)
  }

  function i(t, e, n) {
    return i = u() ? Reflect.construct : function (t, e, n) {
      var r = [null];
      r.push.apply(r, e);
      var o = new (Function.bind.apply(t, r));
      return n && c(o, n.prototype), o
    }, i.apply(null, arguments)
  }

  function u() {
    if ("undefined" == typeof Reflect || !Reflect.construct) return !1;
    if (Reflect.construct.sham) return !1;
    if ("function" == typeof Proxy) return !0;
    try {
      return Boolean.prototype.valueOf.call(Reflect.construct(Boolean, [], (function () {
      }))), !0
    } catch (t) {
      return !1
    }
  }

  function c(t, e) {
    return c = Object.setPrototypeOf || function (t, e) {
      return t.__proto__ = e, t
    }, c(t, e)
  }

  function a(t) {
    return a = Object.setPrototypeOf ? Object.getPrototypeOf : function (t) {
      return t.__proto__ || Object.getPrototypeOf(t)
    }, a(t)
  }

  var l = function (t) {
    !function (t, e) {
      if ("function" != typeof e && null !== e) throw new TypeError("Super expression must either be null or a function");
      t.prototype = Object.create(e && e.prototype, {
        constructor: {
          value: t,
          writable: !0,
          configurable: !0
        }
      }), e && c(t, e)
    }(p, t);
    var o, i, l, s, f = (o = p, i = u(), function () {
      var t, e = a(o);
      if (i) {
        var n = a(this).constructor;
        t = Reflect.construct(e, arguments, n)
      } else t = e.apply(this, arguments);
      return r(this, t)
    });

    function p() {
      return e(this, p), f.apply(this, arguments)
    }

    return l = p, (s = [{
      key: "connectedCallback", value: function () {
        this.build()
      }
    }, {
      key: "build", value: function () {
        this.appendChild(this.createContainer())
      }
    }, {
      key: "createContainer", value: function () {
        var t = document.createElement("div");
        return t.classList.add("mp-input-radio-container"), t.appendChild(this.createRadio()), t.appendChild(this.createLabel()), t
      }
    }, {
      key: "createRadio", value: function () {
        var t = document.createElement("input"), e = this.getAttribute("dataRate");
        return t.classList.add("mp-input-radio-radio"), t.type = "radio", t.id = this.getAttribute("identification"), t.name = this.getAttribute("name"), t.value = this.getAttribute("value"), t.setAttribute("data-cy", "input-radio"), e && t.setAttribute("dataRate", e), t
      }
    }, {
      key: "createLabel", value: function () {
        var t = document.createElement("label");
        return t.classList.add("mp-input-radio-label"), t.htmlFor = this.getAttribute("identification"), t
      }
    }]) && n(l.prototype, s), p
  }(o(HTMLElement));
  customElements.define("input-radio", l)
})(), (() => {
  function t(e) {
    return t = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (t) {
      return typeof t
    } : function (t) {
      return t && "function" == typeof Symbol && t.constructor === Symbol && t !== Symbol.prototype ? "symbol" : typeof t
    }, t(e)
  }

  function e(t, e) {
    if (!(t instanceof e)) throw new TypeError("Cannot call a class as a function")
  }

  function n(t, e) {
    for (var n = 0; n < e.length; n++) {
      var r = e[n];
      r.enumerable = r.enumerable || !1, r.configurable = !0, "value" in r && (r.writable = !0), Object.defineProperty(t, r.key, r)
    }
  }

  function r(e, n) {
    if (n && ("object" === t(n) || "function" == typeof n)) return n;
    if (void 0 !== n) throw new TypeError("Derived constructors may only return object or undefined");
    return function (t) {
      if (void 0 === t) throw new ReferenceError("this hasn't been initialised - super() hasn't been called");
      return t
    }(e)
  }

  function o(t) {
    var e = "function" == typeof Map ? new Map : void 0;
    return o = function (t) {
      if (null === t || (n = t, -1 === Function.toString.call(n).indexOf("[native code]"))) return t;
      var n;
      if ("function" != typeof t) throw new TypeError("Super expression must either be null or a function");
      if (void 0 !== e) {
        if (e.has(t)) return e.get(t);
        e.set(t, r)
      }

      function r() {
        return i(t, arguments, a(this).constructor)
      }

      return r.prototype = Object.create(t.prototype, {
        constructor: {
          value: r,
          enumerable: !1,
          writable: !0,
          configurable: !0
        }
      }), c(r, t)
    }, o(t)
  }

  function i(t, e, n) {
    return i = u() ? Reflect.construct : function (t, e, n) {
      var r = [null];
      r.push.apply(r, e);
      var o = new (Function.bind.apply(t, r));
      return n && c(o, n.prototype), o
    }, i.apply(null, arguments)
  }

  function u() {
    if ("undefined" == typeof Reflect || !Reflect.construct) return !1;
    if (Reflect.construct.sham) return !1;
    if ("function" == typeof Proxy) return !0;
    try {
      return Boolean.prototype.valueOf.call(Reflect.construct(Boolean, [], (function () {
      }))), !0
    } catch (t) {
      return !1
    }
  }

  function c(t, e) {
    return c = Object.setPrototypeOf || function (t, e) {
      return t.__proto__ = e, t
    }, c(t, e)
  }

  function a(t) {
    return a = Object.setPrototypeOf ? Object.getPrototypeOf : function (t) {
      return t.__proto__ || Object.getPrototypeOf(t)
    }, a(t)
  }

  var l = function (t) {
    !function (t, e) {
      if ("function" != typeof e && null !== e) throw new TypeError("Super expression must either be null or a function");
      t.prototype = Object.create(e && e.prototype, {
        constructor: {
          value: t,
          writable: !0,
          configurable: !0
        }
      }), e && c(t, e)
    }(p, t);
    var o, i, l, s, f = (o = p, i = u(), function () {
      var t, e = a(o);
      if (i) {
        var n = a(this).constructor;
        t = Reflect.construct(e, arguments, n)
      } else t = e.apply(this, arguments);
      return r(this, t)
    });

    function p() {
      return e(this, p), f.apply(this, arguments)
    }

    return l = p, (s = [{
      key: "connectedCallback", value: function () {
        this.build()
      }
    }, {
      key: "build", value: function () {
        this.appendChild(this.createContainer())
      }
    }, {
      key: "createContainer", value: function () {
        var t = document.createElement("div");
        return t.classList.add("mp-input-select-container"), t.appendChild(this.createLabel()), t.appendChild(this.createInput()), t
      }
    }, {
      key: "createInput", value: function () {
        var t = document.createElement("div");
        return t.classList.add("mp-input-select-input"), t.appendChild(this.createSelect()), t
      }
    }, {
      key: "createSelect", value: function () {
        var t = this, e = document.createElement("select"), n = this.getAttribute("name");
        e.classList.add("mp-input-select-select"), e.setAttribute("id", n), e.setAttribute("name", n);
        var r = this.getAttribute("options") && JSON.parse(this.getAttribute("options"));
        return r && 0 !== r.length && r.forEach((function (n) {
          e.appendChild(t.createOption(n))
        })), e
      }
    }, {
      key: "createOption", value: function (t) {
        var e = document.createElement("option");
        return e.innerHTML = t, e.value = t, e
      }
    }, {
      key: "createLabel", value: function () {
        var t = document.createElement("input-label"), e = this.getAttribute("optional");
        return t.setAttribute("message", this.getAttribute("label")), "false" === e ? t.setAttribute("isOptional", e) : t.setAttribute("isOptional", "true"), t
      }
    }]) && n(l.prototype, s), p
  }(o(HTMLElement));
  customElements.define("input-select", l)
})(), (() => {
  function t(e) {
    return t = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (t) {
      return typeof t
    } : function (t) {
      return t && "function" == typeof Symbol && t.constructor === Symbol && t !== Symbol.prototype ? "symbol" : typeof t
    }, t(e)
  }

  function e(t, e) {
    for (var n = 0; n < e.length; n++) {
      var r = e[n];
      r.enumerable = r.enumerable || !1, r.configurable = !0, "value" in r && (r.writable = !0), Object.defineProperty(t, r.key, r)
    }
  }

  function n(e, n) {
    if (n && ("object" === t(n) || "function" == typeof n)) return n;
    if (void 0 !== n) throw new TypeError("Derived constructors may only return object or undefined");
    return function (t) {
      if (void 0 === t) throw new ReferenceError("this hasn't been initialised - super() hasn't been called");
      return t
    }(e)
  }

  function r(t) {
    var e = "function" == typeof Map ? new Map : void 0;
    return r = function (t) {
      if (null === t || (n = t, -1 === Function.toString.call(n).indexOf("[native code]"))) return t;
      var n;
      if ("function" != typeof t) throw new TypeError("Super expression must either be null or a function");
      if (void 0 !== e) {
        if (e.has(t)) return e.get(t);
        e.set(t, r)
      }

      function r() {
        return o(t, arguments, c(this).constructor)
      }

      return r.prototype = Object.create(t.prototype, {
        constructor: {
          value: r,
          enumerable: !1,
          writable: !0,
          configurable: !0
        }
      }), u(r, t)
    }, r(t)
  }

  function o(t, e, n) {
    return o = i() ? Reflect.construct : function (t, e, n) {
      var r = [null];
      r.push.apply(r, e);
      var o = new (Function.bind.apply(t, r));
      return n && u(o, n.prototype), o
    }, o.apply(null, arguments)
  }

  function i() {
    if ("undefined" == typeof Reflect || !Reflect.construct) return !1;
    if (Reflect.construct.sham) return !1;
    if ("function" == typeof Proxy) return !0;
    try {
      return Boolean.prototype.valueOf.call(Reflect.construct(Boolean, [], (function () {
      }))), !0
    } catch (t) {
      return !1
    }
  }

  function u(t, e) {
    return u = Object.setPrototypeOf || function (t, e) {
      return t.__proto__ = e, t
    }, u(t, e)
  }

  function c(t) {
    return c = Object.setPrototypeOf ? Object.getPrototypeOf : function (t) {
      return t.__proto__ || Object.getPrototypeOf(t)
    }, c(t)
  }

  var a = function (t) {
    !function (t, e) {
      if ("function" != typeof e && null !== e) throw new TypeError("Super expression must either be null or a function");
      t.prototype = Object.create(e && e.prototype, {
        constructor: {
          value: t,
          writable: !0,
          configurable: !0
        }
      }), e && u(t, e)
    }(p, t);
    var r, o, a, l, s, f = (r = p, o = i(), function () {
      var t, e = c(r);
      if (o) {
        var i = c(this).constructor;
        t = Reflect.construct(e, arguments, i)
      } else t = e.apply(this, arguments);
      return n(this, t)
    });

    function p() {
      var t;
      return function (t, e) {
        if (!(t instanceof e)) throw new TypeError("Cannot call a class as a function")
      }(this, p), (t = f.call(this)).index = 0, t.limit = 5, t.offset = t.limit, t.columns = null, t.total = 0, t
    }

    return a = p, s = [{
      key: "observedAttributes", get: function () {
        return ["columns", "name", "button-name", "bank-interest-text"]
      }
    }], (l = [{
      key: "connectedCallback", value: function () {
        this.build()
      }
    }, {
      key: "attributeChangedCallback", value: function () {
        this.firstElementChild && (this.removeChild(this.firstElementChild), this.build())
      }
    }, {
      key: "build", value: function () {
        this.appendChild(this.createContainer())
      }
    }, {
      key: "setColumns", value: function () {
        return this.columns = JSON.parse(this.getAttribute("columns")), this
      }
    }, {
      key: "setTotal", value: function () {
        return this.total = this.columns.length, this
      }
    }, {
      key: "createContainer", value: function () {
        var t = document.createElement("div");
        return this.setColumns(), this.columns && (this.setTotal(), t.classList.add("mp-input-table-container"), t.setAttribute("data-cy", "input-table-container"), t.appendChild(this.createList()), t.appendChild(this.createBankInterestDisclaimer())), t
      }
    }, {
      key: "createList", value: function () {
        var t = this, e = document.createElement("div");
        e.classList.add("mp-input-table-list"), e.setAttribute("data-cy", "input-table-list");
        var n = this.createLink();
        return n.onclick = function () {
          return t.appendItems(t.columns, e, n, !0)
        }, this.appendItems(this.columns, e, n, !1), e.appendChild(n), e
      }
    }, {
      key: "createItem", value: function (t) {
        var e = document.createElement("div");
        return e.classList.add("mp-input-table-item"), e.appendChild(this.createLabel(t)), e
      }
    }, {
      key: "createLabel", value: function (t) {
        var e = t.id, n = t.value, r = t.rowText, o = t.rowObs, i = t.highlight, u = t.img, c = t.alt, a = t.dataRate,
          l = this.getAttribute("name"), s = document.createElement("div");
        return s.classList.add("mp-input-table-label"), s.appendChild(this.createOption(e, l, n, r, u, c, a)), o && s.appendChild(this.createRowObs(o, i)), s.onclick = function () {
          document.getElementById(e).checked = !0
        }, s
      }
    }, {
      key: "createOption", value: function (t, e, n, r, o, i, u) {
        var c = document.createElement("div");
        return c.classList.add("mp-input-table-option"), c.appendChild(this.createRadio(t, e, n, u)), o ? c.appendChild(this.createRowTextWithImg(r, o, i)) : c.appendChild(this.createRowText(r)), c
      }
    }, {
      key: "createRadio", value: function (t, e, n, r) {
        var o = document.createElement("input-radio");
        return o.setAttribute("name", e), o.setAttribute("value", n), o.setAttribute("identification", t), o.setAttribute("dataRate", r), o
      }
    }, {
      key: "createRowText", value: function (t) {
        var e = document.createElement("span");
        return e.classList.add("mp-input-table-row-text"), e.innerHTML = t, e
      }
    }, {
      key: "createRowTextWithImg", value: function (t, e, n) {
        var r = document.createElement("span"), o = document.createElement("payment-method-logo");
        return o.setAttribute("src", e), o.setAttribute("alt", n), o.style.marginRight = "10px", r.classList.add("mp-input-table-row-text-image"), r.innerHTML = t, r.appendChild(o), r
      }
    }, {
      key: "createRowObs", value: function (t, e) {
        var n = document.createElement("span");
        return e ? n.classList.add("mp-input-table-row-obs-highlight") : n.classList.add("mp-input-table-row-obs"), n.innerHTML = t, n
      }
    }, {
      key: "createLink", value: function () {
        var t = document.createElement("div");
        t.classList.add("mp-input-table-container-link");
        var e = document.createElement("a");
        return e.setAttribute("id", "more-options"), e.classList.add("mp-input-table-link"), e.innerHTML = this.getAttribute("button-name"), t.appendChild(e), t
      }
    }, {
      key: "createBankInterestDisclaimer", value: function () {
        var t = document.createElement("div");
        t.classList.add("mp-input-table-bank-interest-container");
        var e = document.createElement("p");
        return e.classList.add("mp-input-table-bank-interest-text"), e.innerText = this.getAttribute("bank-interest-text"), t.appendChild(e), t
      }
    }, {
      key: "appendItems", value: function (t, e, n, r) {
        this.validateLimit();
        for (var o = this.index; o < this.limit; o += 1) r ? e.insertBefore(this.createItem(t[o]), n) : e.appendChild(this.createItem(t[o]));
        this.limit >= this.total && n.style.setProperty("display", "none", "important"), this.index += this.offset, this.limit += this.offset, this.validateLimit()
      }
    }, {
      key: "validateLimit", value: function () {
        this.limit > this.total && (this.limit = this.total)
      }
    }]) && e(a.prototype, l), s && e(a, s), p
  }(r(HTMLElement));
  customElements.define("input-table", a)
})(), (() => {
  function t(e) {
    return t = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (t) {
      return typeof t
    } : function (t) {
      return t && "function" == typeof Symbol && t.constructor === Symbol && t !== Symbol.prototype ? "symbol" : typeof t
    }, t(e)
  }

  function e(t, e) {
    if (!(t instanceof e)) throw new TypeError("Cannot call a class as a function")
  }

  function n(t, e) {
    for (var n = 0; n < e.length; n++) {
      var r = e[n];
      r.enumerable = r.enumerable || !1, r.configurable = !0, "value" in r && (r.writable = !0), Object.defineProperty(t, r.key, r)
    }
  }

  function r(e, n) {
    if (n && ("object" === t(n) || "function" == typeof n)) return n;
    if (void 0 !== n) throw new TypeError("Derived constructors may only return object or undefined");
    return function (t) {
      if (void 0 === t) throw new ReferenceError("this hasn't been initialised - super() hasn't been called");
      return t
    }(e)
  }

  function o(t) {
    var e = "function" == typeof Map ? new Map : void 0;
    return o = function (t) {
      if (null === t || (n = t, -1 === Function.toString.call(n).indexOf("[native code]"))) return t;
      var n;
      if ("function" != typeof t) throw new TypeError("Super expression must either be null or a function");
      if (void 0 !== e) {
        if (e.has(t)) return e.get(t);
        e.set(t, r)
      }

      function r() {
        return i(t, arguments, a(this).constructor)
      }

      return r.prototype = Object.create(t.prototype, {
        constructor: {
          value: r,
          enumerable: !1,
          writable: !0,
          configurable: !0
        }
      }), c(r, t)
    }, o(t)
  }

  function i(t, e, n) {
    return i = u() ? Reflect.construct : function (t, e, n) {
      var r = [null];
      r.push.apply(r, e);
      var o = new (Function.bind.apply(t, r));
      return n && c(o, n.prototype), o
    }, i.apply(null, arguments)
  }

  function u() {
    if ("undefined" == typeof Reflect || !Reflect.construct) return !1;
    if (Reflect.construct.sham) return !1;
    if ("function" == typeof Proxy) return !0;
    try {
      return Boolean.prototype.valueOf.call(Reflect.construct(Boolean, [], (function () {
      }))), !0
    } catch (t) {
      return !1
    }
  }

  function c(t, e) {
    return c = Object.setPrototypeOf || function (t, e) {
      return t.__proto__ = e, t
    }, c(t, e)
  }

  function a(t) {
    return a = Object.setPrototypeOf ? Object.getPrototypeOf : function (t) {
      return t.__proto__ || Object.getPrototypeOf(t)
    }, a(t)
  }

  var l = function (t) {
    !function (t, e) {
      if ("function" != typeof e && null !== e) throw new TypeError("Super expression must either be null or a function");
      t.prototype = Object.create(e && e.prototype, {
        constructor: {
          value: t,
          writable: !0,
          configurable: !0
        }
      }), e && c(t, e)
    }(p, t);
    var o, i, l, s, f = (o = p, i = u(), function () {
      var t, e = a(o);
      if (i) {
        var n = a(this).constructor;
        t = Reflect.construct(e, arguments, n)
      } else t = e.apply(this, arguments);
      return r(this, t)
    });

    function p() {
      return e(this, p), f.apply(this, arguments)
    }

    return l = p, s = [{
      key: "connectedCallback", value: function () {
        this.build()
      }
    }, {
      key: "build", value: function () {
        this.appendChild(this.createContainer())
      }
    }, {
      key: "createContainer", value: function () {
        var t = document.createElement("div");
        return t.classList.add("mp-payment-method-logo-slider-container"), t.appendChild(this.createContent()), t
      }
    }, {
      key: "createContent", value: function () {
        var t = JSON.parse(this.getAttribute("methods")), e = document.createElement("div");
        e.classList.add("mp-payment-method-logo-slider-content"), e.appendChild(this.createImage(t[0]));
        var n = e.firstChild;
        return this.createSlider(n, t), e
      }
    }, {
      key: "createImage", value: function (t) {
        var e = t.src, n = t.alt, r = document.createElement("payment-method-logo");
        return r.setAttribute("src", e), r.setAttribute("alt", n), r
      }
    }, {
      key: "createSlider", value: function (t, e) {
        var n = this, r = arguments.length > 2 && void 0 !== arguments[2] ? arguments[2] : 0;
        t.setAttribute("src", e[r].src), t.setAttribute("alt", e[r].alt), r = r < e.length - 1 ? r + 1 : 0, setTimeout((function () {
          n.createSlider(t, e, r)
        }), 2e3)
      }
    }], s && n(l.prototype, s), p
  }(o(HTMLElement));
  customElements.define("payment-method-logo-slider", l)
})(), (() => {
  function t(e) {
    return t = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (t) {
      return typeof t
    } : function (t) {
      return t && "function" == typeof Symbol && t.constructor === Symbol && t !== Symbol.prototype ? "symbol" : typeof t
    }, t(e)
  }

  function e(t, e) {
    if (!(t instanceof e)) throw new TypeError("Cannot call a class as a function")
  }

  function n(t, e) {
    for (var n = 0; n < e.length; n++) {
      var r = e[n];
      r.enumerable = r.enumerable || !1, r.configurable = !0, "value" in r && (r.writable = !0), Object.defineProperty(t, r.key, r)
    }
  }

  function r(e, n) {
    if (n && ("object" === t(n) || "function" == typeof n)) return n;
    if (void 0 !== n) throw new TypeError("Derived constructors may only return object or undefined");
    return function (t) {
      if (void 0 === t) throw new ReferenceError("this hasn't been initialised - super() hasn't been called");
      return t
    }(e)
  }

  function o(t) {
    var e = "function" == typeof Map ? new Map : void 0;
    return o = function (t) {
      if (null === t || (n = t, -1 === Function.toString.call(n).indexOf("[native code]"))) return t;
      var n;
      if ("function" != typeof t) throw new TypeError("Super expression must either be null or a function");
      if (void 0 !== e) {
        if (e.has(t)) return e.get(t);
        e.set(t, r)
      }

      function r() {
        return i(t, arguments, a(this).constructor)
      }

      return r.prototype = Object.create(t.prototype, {
        constructor: {
          value: r,
          enumerable: !1,
          writable: !0,
          configurable: !0
        }
      }), c(r, t)
    }, o(t)
  }

  function i(t, e, n) {
    return i = u() ? Reflect.construct : function (t, e, n) {
      var r = [null];
      r.push.apply(r, e);
      var o = new (Function.bind.apply(t, r));
      return n && c(o, n.prototype), o
    }, i.apply(null, arguments)
  }

  function u() {
    if ("undefined" == typeof Reflect || !Reflect.construct) return !1;
    if (Reflect.construct.sham) return !1;
    if ("function" == typeof Proxy) return !0;
    try {
      return Boolean.prototype.valueOf.call(Reflect.construct(Boolean, [], (function () {
      }))), !0
    } catch (t) {
      return !1
    }
  }

  function c(t, e) {
    return c = Object.setPrototypeOf || function (t, e) {
      return t.__proto__ = e, t
    }, c(t, e)
  }

  function a(t) {
    return a = Object.setPrototypeOf ? Object.getPrototypeOf : function (t) {
      return t.__proto__ || Object.getPrototypeOf(t)
    }, a(t)
  }

  var l = function (t) {
    !function (t, e) {
      if ("function" != typeof e && null !== e) throw new TypeError("Super expression must either be null or a function");
      t.prototype = Object.create(e && e.prototype, {
        constructor: {
          value: t,
          writable: !0,
          configurable: !0
        }
      }), e && c(t, e)
    }(d, t);
    var o, i, l, s, f, p = (o = d, i = u(), function () {
      var t, e = a(o);
      if (i) {
        var n = a(this).constructor;
        t = Reflect.construct(e, arguments, n)
      } else t = e.apply(this, arguments);
      return r(this, t)
    });

    function d() {
      return e(this, d), p.apply(this, arguments)
    }

    return l = d, f = [{
      key: "observedAttributes", get: function () {
        return ["src", "alt"]
      }
    }], (s = [{
      key: "connectedCallback", value: function () {
        this.build()
      }
    }, {
      key: "attributeChangedCallback", value: function () {
        this.firstElementChild && (this.removeChild(this.firstElementChild), this.build())
      }
    }, {
      key: "build", value: function () {
        this.appendChild(this.createContainer())
      }
    }, {
      key: "createContainer", value: function () {
        var t = document.createElement("div");
        return t.classList.add("mp-payment-method-logo-container"), t.appendChild(this.createImage()), t
      }
    }, {
      key: "createImage", value: function () {
        var t = document.createElement("img");
        return t.classList.add("mp-payment-method-logo-image"), t.alt = this.getAttribute("alt"), t.src = this.getAttribute("src"), t.onerror = function (t) {
          var e, n, r, o;
          return null === (e = t.target) || void 0 === e || null === (n = e.parentNode) || void 0 === n || null === (r = n.parentNode) || void 0 === r || null === (o = r.parentNode) || void 0 === o ? void 0 : o.removeChild(t.target.parentNode.parentNode)
        }, t
      }
    }]) && n(l.prototype, s), f && n(l, f), d
  }(o(HTMLElement));
  customElements.define("payment-method-logo", l)
})(), (() => {
  function t(e) {
    return t = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (t) {
      return typeof t
    } : function (t) {
      return t && "function" == typeof Symbol && t.constructor === Symbol && t !== Symbol.prototype ? "symbol" : typeof t
    }, t(e)
  }

  function e(t, e) {
    if (!(t instanceof e)) throw new TypeError("Cannot call a class as a function")
  }

  function n(t, e) {
    for (var n = 0; n < e.length; n++) {
      var r = e[n];
      r.enumerable = r.enumerable || !1, r.configurable = !0, "value" in r && (r.writable = !0), Object.defineProperty(t, r.key, r)
    }
  }

  function r(e, n) {
    if (n && ("object" === t(n) || "function" == typeof n)) return n;
    if (void 0 !== n) throw new TypeError("Derived constructors may only return object or undefined");
    return function (t) {
      if (void 0 === t) throw new ReferenceError("this hasn't been initialised - super() hasn't been called");
      return t
    }(e)
  }

  function o(t) {
    var e = "function" == typeof Map ? new Map : void 0;
    return o = function (t) {
      if (null === t || (n = t, -1 === Function.toString.call(n).indexOf("[native code]"))) return t;
      var n;
      if ("function" != typeof t) throw new TypeError("Super expression must either be null or a function");
      if (void 0 !== e) {
        if (e.has(t)) return e.get(t);
        e.set(t, r)
      }

      function r() {
        return i(t, arguments, a(this).constructor)
      }

      return r.prototype = Object.create(t.prototype, {
        constructor: {
          value: r,
          enumerable: !1,
          writable: !0,
          configurable: !0
        }
      }), c(r, t)
    }, o(t)
  }

  function i(t, e, n) {
    return i = u() ? Reflect.construct : function (t, e, n) {
      var r = [null];
      r.push.apply(r, e);
      var o = new (Function.bind.apply(t, r));
      return n && c(o, n.prototype), o
    }, i.apply(null, arguments)
  }

  function u() {
    if ("undefined" == typeof Reflect || !Reflect.construct) return !1;
    if (Reflect.construct.sham) return !1;
    if ("function" == typeof Proxy) return !0;
    try {
      return Boolean.prototype.valueOf.call(Reflect.construct(Boolean, [], (function () {
      }))), !0
    } catch (t) {
      return !1
    }
  }

  function c(t, e) {
    return c = Object.setPrototypeOf || function (t, e) {
      return t.__proto__ = e, t
    }, c(t, e)
  }

  function a(t) {
    return a = Object.setPrototypeOf ? Object.getPrototypeOf : function (t) {
      return t.__proto__ || Object.getPrototypeOf(t)
    }, a(t)
  }

  var l = function (t) {
    !function (t, e) {
      if ("function" != typeof e && null !== e) throw new TypeError("Super expression must either be null or a function");
      t.prototype = Object.create(e && e.prototype, {
        constructor: {
          value: t,
          writable: !0,
          configurable: !0
        }
      }), e && c(t, e)
    }(p, t);
    var o, i, l, s, f = (o = p, i = u(), function () {
      var t, e = a(o);
      if (i) {
        var n = a(this).constructor;
        t = Reflect.construct(e, arguments, n)
      } else t = e.apply(this, arguments);
      return r(this, t)
    });

    function p() {
      return e(this, p), f.apply(this, arguments)
    }

    return l = p, (s = [{
      key: "connectedCallback", value: function () {
        this.build()
      }
    }, {
      key: "build", value: function () {
        this.appendChild(this.createContainer())
      }
    }, {
      key: "createContainer", value: function () {
        var t = document.createElement("div");
        return t.classList.add("mp-payment-methods-v2-container"), t.appendChild(this.createContent()), t
      }
    }, {
      key: "createContent", value: function () {
        var t = document.createElement("div");
        return t.classList.add("mp-payment-methods-v2-content"), t.appendChild(this.createTitle()), t.appendChild(this.createList()), t
      }
    }, {
      key: "createTitle", value: function () {
        var t = document.createElement("p");
        return t.classList.add("mp-payment-methods-v2-title"), t.innerHTML = this.getAttribute("title"), t
      }
    }, {
      key: "createList", value: function () {
        var t = document.createElement("div");
        return t.classList.add("mp-payment-methods-v2-list"), this.handleMethodsList(t)
      }
    }, {
      key: "handleMethodsList", value: function (t) {
        var e = this, n = JSON.parse(this.getAttribute("methods")), r = !1;
        if (n.forEach((function (o, i) {
          i <= 9 || 11 === n.length ? t.appendChild(e.createLogo(o)) : r = !0
        })), r) {
          var o = Object.entries(n).slice(10).map((function (t) {
            return t[1]
          }));
          t.appendChild(this.createSlider(JSON.stringify(o)))
        }
        return t
      }
    }, {
      key: "createLogo", value: function (t) {
        var e = t.src, n = t.alt, r = document.createElement("payment-method-logo");
        return r.setAttribute("src", e), r.setAttribute("alt", n), r
      }
    }, {
      key: "createSlider", value: function (t) {
        var e = document.createElement("payment-method-logo-slider");
        return e.setAttribute("methods", t), e
      }
    }]) && n(l.prototype, s), p
  }(o(HTMLElement));
  customElements.define("payment-methods-v2", l)
})(), (() => {
  function t(e) {
    return t = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (t) {
      return typeof t
    } : function (t) {
      return t && "function" == typeof Symbol && t.constructor === Symbol && t !== Symbol.prototype ? "symbol" : typeof t
    }, t(e)
  }

  function e(t, e) {
    if (!(t instanceof e)) throw new TypeError("Cannot call a class as a function")
  }

  function n(t, e) {
    for (var n = 0; n < e.length; n++) {
      var r = e[n];
      r.enumerable = r.enumerable || !1, r.configurable = !0, "value" in r && (r.writable = !0), Object.defineProperty(t, r.key, r)
    }
  }

  function r(e, n) {
    if (n && ("object" === t(n) || "function" == typeof n)) return n;
    if (void 0 !== n) throw new TypeError("Derived constructors may only return object or undefined");
    return function (t) {
      if (void 0 === t) throw new ReferenceError("this hasn't been initialised - super() hasn't been called");
      return t
    }(e)
  }

  function o(t) {
    var e = "function" == typeof Map ? new Map : void 0;
    return o = function (t) {
      if (null === t || (n = t, -1 === Function.toString.call(n).indexOf("[native code]"))) return t;
      var n;
      if ("function" != typeof t) throw new TypeError("Super expression must either be null or a function");
      if (void 0 !== e) {
        if (e.has(t)) return e.get(t);
        e.set(t, r)
      }

      function r() {
        return i(t, arguments, a(this).constructor)
      }

      return r.prototype = Object.create(t.prototype, {
        constructor: {
          value: r,
          enumerable: !1,
          writable: !0,
          configurable: !0
        }
      }), c(r, t)
    }, o(t)
  }

  function i(t, e, n) {
    return i = u() ? Reflect.construct : function (t, e, n) {
      var r = [null];
      r.push.apply(r, e);
      var o = new (Function.bind.apply(t, r));
      return n && c(o, n.prototype), o
    }, i.apply(null, arguments)
  }

  function u() {
    if ("undefined" == typeof Reflect || !Reflect.construct) return !1;
    if (Reflect.construct.sham) return !1;
    if ("function" == typeof Proxy) return !0;
    try {
      return Boolean.prototype.valueOf.call(Reflect.construct(Boolean, [], (function () {
      }))), !0
    } catch (t) {
      return !1
    }
  }

  function c(t, e) {
    return c = Object.setPrototypeOf || function (t, e) {
      return t.__proto__ = e, t
    }, c(t, e)
  }

  function a(t) {
    return a = Object.setPrototypeOf ? Object.getPrototypeOf : function (t) {
      return t.__proto__ || Object.getPrototypeOf(t)
    }, a(t)
  }

  var l = function (t) {
    !function (t, e) {
      if ("function" != typeof e && null !== e) throw new TypeError("Super expression must either be null or a function");
      t.prototype = Object.create(e && e.prototype, {
        constructor: {
          value: t,
          writable: !0,
          configurable: !0
        }
      }), e && c(t, e)
    }(p, t);
    var o, i, l, s, f = (o = p, i = u(), function () {
      var t, e = a(o);
      if (i) {
        var n = a(this).constructor;
        t = Reflect.construct(e, arguments, n)
      } else t = e.apply(this, arguments);
      return r(this, t)
    });

    function p() {
      return e(this, p), f.apply(this, arguments)
    }

    return l = p, (s = [{
      key: "connectedCallback", value: function () {
        this.build()
      }
    }, {
      key: "build", value: function () {
        this.appendChild(this.createContainer())
      }
    }, {
      key: "createContainer", value: function () {
        var t = this, e = JSON.parse(this.getAttribute("methods")), n = document.createElement("div");
        return n.classList.add("mp-payment-methods-container"), e.forEach((function (e) {
          n.appendChild(t.createPaymentMethodType(e))
        })), n
      }
    }, {
      key: "createPaymentMethodType", value: function (t) {
        var e = t.title, n = t.label, r = t.payment_methods, o = document.createElement("div");
        return o.classList.add("mp-payment-method-type-container"), r && 0 !== r.length && (o.appendChild(this.createHeader(e, n)), o.appendChild(this.createContent(r))), o
      }
    }, {
      key: "createHeader", value: function (t, e) {
        var n = document.createElement("div");
        return n.classList.add("mp-payment-methods-header"), t && n.appendChild(this.createTitle(t)), e && n.appendChild(this.createBadge(e)), n
      }
    }, {
      key: "createTitle", value: function (t) {
        var e = document.createElement("p");
        return e.classList.add("mp-payment-methods-title"), e.innerHTML = t, e
      }
    }, {
      key: "createBadge", value: function (t) {
        var e = document.createElement("div"), n = document.createElement("span");
        return n.classList.add("mp-payment-methods-badge-text"), n.innerHTML = t, e.classList.add("mp-payment-methods-badge"), e.appendChild(n), e
      }
    }, {
      key: "createContent", value: function (t) {
        var e = this, n = document.createElement("div");
        return n.classList.add("mp-payment-methods-content"), t.forEach((function (t) {
          n.appendChild(e.createImage(t))
        })), n
      }
    }, {
      key: "createImage", value: function (t) {
        var e = t.src, n = t.alt, r = document.createElement("payment-method-logo");
        return r.setAttribute("src", e), r.setAttribute("alt", n), r
      }
    }]) && n(l.prototype, s), p
  }(o(HTMLElement));
  customElements.define("payment-methods", l)
})(), (() => {
  function t(e) {
    return t = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (t) {
      return typeof t
    } : function (t) {
      return t && "function" == typeof Symbol && t.constructor === Symbol && t !== Symbol.prototype ? "symbol" : typeof t
    }, t(e)
  }

  function e(t, e) {
    if (!(t instanceof e)) throw new TypeError("Cannot call a class as a function")
  }

  function n(t, e) {
    for (var n = 0; n < e.length; n++) {
      var r = e[n];
      r.enumerable = r.enumerable || !1, r.configurable = !0, "value" in r && (r.writable = !0), Object.defineProperty(t, r.key, r)
    }
  }

  function r(e, n) {
    if (n && ("object" === t(n) || "function" == typeof n)) return n;
    if (void 0 !== n) throw new TypeError("Derived constructors may only return object or undefined");
    return function (t) {
      if (void 0 === t) throw new ReferenceError("this hasn't been initialised - super() hasn't been called");
      return t
    }(e)
  }

  function o(t) {
    var e = "function" == typeof Map ? new Map : void 0;
    return o = function (t) {
      if (null === t || (n = t, -1 === Function.toString.call(n).indexOf("[native code]"))) return t;
      var n;
      if ("function" != typeof t) throw new TypeError("Super expression must either be null or a function");
      if (void 0 !== e) {
        if (e.has(t)) return e.get(t);
        e.set(t, r)
      }

      function r() {
        return i(t, arguments, a(this).constructor)
      }

      return r.prototype = Object.create(t.prototype, {
        constructor: {
          value: r,
          enumerable: !1,
          writable: !0,
          configurable: !0
        }
      }), c(r, t)
    }, o(t)
  }

  function i(t, e, n) {
    return i = u() ? Reflect.construct : function (t, e, n) {
      var r = [null];
      r.push.apply(r, e);
      var o = new (Function.bind.apply(t, r));
      return n && c(o, n.prototype), o
    }, i.apply(null, arguments)
  }

  function u() {
    if ("undefined" == typeof Reflect || !Reflect.construct) return !1;
    if (Reflect.construct.sham) return !1;
    if ("function" == typeof Proxy) return !0;
    try {
      return Boolean.prototype.valueOf.call(Reflect.construct(Boolean, [], (function () {
      }))), !0
    } catch (t) {
      return !1
    }
  }

  function c(t, e) {
    return c = Object.setPrototypeOf || function (t, e) {
      return t.__proto__ = e, t
    }, c(t, e)
  }

  function a(t) {
    return a = Object.setPrototypeOf ? Object.getPrototypeOf : function (t) {
      return t.__proto__ || Object.getPrototypeOf(t)
    }, a(t)
  }

  var l = function (t) {
    !function (t, e) {
      if ("function" != typeof e && null !== e) throw new TypeError("Super expression must either be null or a function");
      t.prototype = Object.create(e && e.prototype, {
        constructor: {
          value: t,
          writable: !0,
          configurable: !0
        }
      }), e && c(t, e)
    }(p, t);
    var o, i, l, s, f = (o = p, i = u(), function () {
      var t, e = a(o);
      if (i) {
        var n = a(this).constructor;
        t = Reflect.construct(e, arguments, n)
      } else t = e.apply(this, arguments);
      return r(this, t)
    });

    function p() {
      return e(this, p), f.apply(this, arguments)
    }

    return l = p, (s = [{
      key: "connectedCallback", value: function () {
        this.build()
      }
    }, {
      key: "build", value: function () {
        this.appendChild(this.createContainer())
      }
    }, {
      key: "createContainer", value: function () {
        var t = document.createElement("div");
        return t.classList.add("mp-pix-template-container"), t.setAttribute("data-cy", "pix-template-container"), t.appendChild(this.createImage()), t.appendChild(this.createTitle()), t.appendChild(this.createSubtitle()), t
      }
    }, {
      key: "createTitle", value: function () {
        var t = document.createElement("p");
        return t.classList.add("mp-pix-template-title"), t.innerText = this.getAttribute("title"), t
      }
    }, {
      key: "createSubtitle", value: function () {
        var t = document.createElement("p");
        return t.classList.add("mp-pix-template-subtitle"), t.innerText = this.getAttribute("subtitle"), t
      }
    }, {
      key: "createImage", value: function () {
        var t = document.createElement("img");
        return t.classList.add("mp-pix-template-image"), t.src = this.getAttribute("src"), t.alt = this.getAttribute("alt"), t
      }
    }]) && n(l.prototype, s), p
  }(o(HTMLElement));
  customElements.define("pix-template", l)
})(), (() => {
  function t(e) {
    return t = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (t) {
      return typeof t
    } : function (t) {
      return t && "function" == typeof Symbol && t.constructor === Symbol && t !== Symbol.prototype ? "symbol" : typeof t
    }, t(e)
  }

  function e(t, e) {
    if (!(t instanceof e)) throw new TypeError("Cannot call a class as a function")
  }

  function n(t, e) {
    for (var n = 0; n < e.length; n++) {
      var r = e[n];
      r.enumerable = r.enumerable || !1, r.configurable = !0, "value" in r && (r.writable = !0), Object.defineProperty(t, r.key, r)
    }
  }

  function r(e, n) {
    if (n && ("object" === t(n) || "function" == typeof n)) return n;
    if (void 0 !== n) throw new TypeError("Derived constructors may only return object or undefined");
    return function (t) {
      if (void 0 === t) throw new ReferenceError("this hasn't been initialised - super() hasn't been called");
      return t
    }(e)
  }

  function o(t) {
    var e = "function" == typeof Map ? new Map : void 0;
    return o = function (t) {
      if (null === t || (n = t, -1 === Function.toString.call(n).indexOf("[native code]"))) return t;
      var n;
      if ("function" != typeof t) throw new TypeError("Super expression must either be null or a function");
      if (void 0 !== e) {
        if (e.has(t)) return e.get(t);
        e.set(t, r)
      }

      function r() {
        return i(t, arguments, a(this).constructor)
      }

      return r.prototype = Object.create(t.prototype, {
        constructor: {
          value: r,
          enumerable: !1,
          writable: !0,
          configurable: !0
        }
      }), c(r, t)
    }, o(t)
  }

  function i(t, e, n) {
    return i = u() ? Reflect.construct : function (t, e, n) {
      var r = [null];
      r.push.apply(r, e);
      var o = new (Function.bind.apply(t, r));
      return n && c(o, n.prototype), o
    }, i.apply(null, arguments)
  }

  function u() {
    if ("undefined" == typeof Reflect || !Reflect.construct) return !1;
    if (Reflect.construct.sham) return !1;
    if ("function" == typeof Proxy) return !0;
    try {
      return Boolean.prototype.valueOf.call(Reflect.construct(Boolean, [], (function () {
      }))), !0
    } catch (t) {
      return !1
    }
  }

  function c(t, e) {
    return c = Object.setPrototypeOf || function (t, e) {
      return t.__proto__ = e, t
    }, c(t, e)
  }

  function a(t) {
    return a = Object.setPrototypeOf ? Object.getPrototypeOf : function (t) {
      return t.__proto__ || Object.getPrototypeOf(t)
    }, a(t)
  }

  var l = function (t) {
    !function (t, e) {
      if ("function" != typeof e && null !== e) throw new TypeError("Super expression must either be null or a function");
      t.prototype = Object.create(e && e.prototype, {
        constructor: {
          value: t,
          writable: !0,
          configurable: !0
        }
      }), e && c(t, e)
    }(p, t);
    var o, i, l, s, f = (o = p, i = u(), function () {
      var t, e = a(o);
      if (i) {
        var n = a(this).constructor;
        t = Reflect.construct(e, arguments, n)
      } else t = e.apply(this, arguments);
      return r(this, t)
    });

    function p() {
      return e(this, p), f.apply(this, arguments)
    }

    return l = p, (s = [{
      key: "connectedCallback", value: function () {
        this.build()
      }
    }, {
      key: "build", value: function () {
        this.appendChild(this.createContainer())
      }
    }, {
      key: "createContainer", value: function () {
        var t = document.createElement("div");
        return t.classList.add("mp-terms-and-conditions-container"), t.setAttribute("data-cy", "terms-and-conditions-container"), t.appendChild(this.createText()), t.appendChild(this.createLink()), t
      }
    }, {
      key: "createText", value: function () {
        var t = document.createElement("span");
        return t.classList.add("mp-terms-and-conditions-text"), t.innerHTML = this.getAttribute("description"), t
      }
    }, {
      key: "createLink", value: function () {
        var t = document.createElement("a");
        return t.classList.add("mp-terms-and-conditions-link"), t.innerHTML = this.getAttribute("link-text"), t.href = this.getAttribute("link-src"), t.target = "blank", t
      }
    }]) && n(l.prototype, s), p
  }(o(HTMLElement));
  customElements.define("terms-and-conditions", l)
})(), (() => {
  function t(e) {
    return t = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (t) {
      return typeof t
    } : function (t) {
      return t && "function" == typeof Symbol && t.constructor === Symbol && t !== Symbol.prototype ? "symbol" : typeof t
    }, t(e)
  }

  function e(t, e) {
    if (!(t instanceof e)) throw new TypeError("Cannot call a class as a function")
  }

  function n(t, e) {
    for (var n = 0; n < e.length; n++) {
      var r = e[n];
      r.enumerable = r.enumerable || !1, r.configurable = !0, "value" in r && (r.writable = !0), Object.defineProperty(t, r.key, r)
    }
  }

  function r(e, n) {
    if (n && ("object" === t(n) || "function" == typeof n)) return n;
    if (void 0 !== n) throw new TypeError("Derived constructors may only return object or undefined");
    return function (t) {
      if (void 0 === t) throw new ReferenceError("this hasn't been initialised - super() hasn't been called");
      return t
    }(e)
  }

  function o(t) {
    var e = "function" == typeof Map ? new Map : void 0;
    return o = function (t) {
      if (null === t || (n = t, -1 === Function.toString.call(n).indexOf("[native code]"))) return t;
      var n;
      if ("function" != typeof t) throw new TypeError("Super expression must either be null or a function");
      if (void 0 !== e) {
        if (e.has(t)) return e.get(t);
        e.set(t, r)
      }

      function r() {
        return i(t, arguments, a(this).constructor)
      }

      return r.prototype = Object.create(t.prototype, {
        constructor: {
          value: r,
          enumerable: !1,
          writable: !0,
          configurable: !0
        }
      }), c(r, t)
    }, o(t)
  }

  function i(t, e, n) {
    return i = u() ? Reflect.construct : function (t, e, n) {
      var r = [null];
      r.push.apply(r, e);
      var o = new (Function.bind.apply(t, r));
      return n && c(o, n.prototype), o
    }, i.apply(null, arguments)
  }

  function u() {
    if ("undefined" == typeof Reflect || !Reflect.construct) return !1;
    if (Reflect.construct.sham) return !1;
    if ("function" == typeof Proxy) return !0;
    try {
      return Boolean.prototype.valueOf.call(Reflect.construct(Boolean, [], (function () {
      }))), !0
    } catch (t) {
      return !1
    }
  }

  function c(t, e) {
    return c = Object.setPrototypeOf || function (t, e) {
      return t.__proto__ = e, t
    }, c(t, e)
  }

  function a(t) {
    return a = Object.setPrototypeOf ? Object.getPrototypeOf : function (t) {
      return t.__proto__ || Object.getPrototypeOf(t)
    }, a(t)
  }

  var l = function (t) {
    !function (t, e) {
      if ("function" != typeof e && null !== e) throw new TypeError("Super expression must either be null or a function");
      t.prototype = Object.create(e && e.prototype, {
        constructor: {
          value: t,
          writable: !0,
          configurable: !0
        }
      }), e && c(t, e)
    }(p, t);
    var o, i, l, s, f = (o = p, i = u(), function () {
      var t, e = a(o);
      if (i) {
        var n = a(this).constructor;
        t = Reflect.construct(e, arguments, n)
      } else t = e.apply(this, arguments);
      return r(this, t)
    });

    function p() {
      return e(this, p), f.apply(this, arguments)
    }

    return l = p, (s = [{
      key: "connectedCallback", value: function () {
        this.build()
      }
    }, {
      key: "build", value: function () {
        var t = this.createTestMode(), e = this.createCardHeader(), n = this.createCardContent();
        t.appendChild(e), t.appendChild(n), this.appendChild(t)
      }
    }, {
      key: "createTestMode", value: function () {
        var t = document.createElement("div");
        return t.classList.add("mp-test-mode-card"), t.setAttribute("data-cy", "test-mode-card"), t
      }
    }, {
      key: "createCardContent", value: function () {
        var t = document.createElement("div");
        t.classList.add("mp-test-mode-card-content");
        var e = document.createElement("p");
        e.innerHTML = this.getAttribute("description"), e.classList.add("mp-test-mode-description"), e.setAttribute("data-cy", "test-mode-description"), t.appendChild(e);
        var n = this.getAttribute("link-text"), r = this.getAttribute("link-src"), o = document.createElement("a");
        return o.classList.add("mp-test-mode-link"), o.innerHTML = n, o.href = r, o.target = "blank", e.appendChild(o), t
      }
    }, {
      key: "createCardHeader", value: function () {
        var t = document.createElement("div");
        t.classList.add("mp-test-mode-card-content");
        var e = this.createBadge(), n = this.createTitle();
        return t.appendChild(e), t.appendChild(n), t
      }
    }, {
      key: "createBadge", value: function () {
        var t = document.createElement("div");
        return t.innerHTML = "!", t.classList.add("mp-test-mode-badge"), t
      }
    }, {
      key: "createTitle", value: function () {
        var t = document.createElement("p");
        return t.innerHTML = this.getAttribute("title"), t.classList.add("mp-test-mode-title"), t.setAttribute("data-cy", "test-mode-title"), t
      }
    }]) && n(l.prototype, s), p
  }(o(HTMLElement));
  customElements.define("test-mode", l)
})();
