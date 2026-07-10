/* ERP CFA — moteur de rendu de la maquette (statique) */
(function () {
  "use strict";

  var DATA = window.ERP_DATA;
  var espaces = DATA.espaces;

  var TONE = {
    green: "bg-emerald-100 text-emerald-700",
    amber: "bg-amber-100 text-amber-700",
    red:   "bg-red-100 text-red-700",
    blue:  "bg-sky-100 text-sky-700",
    gray:  "bg-slate-200 text-slate-600",
    "":    "bg-slate-100 text-slate-600",
  };
  var DOT = {
    green: "bg-emerald-500", amber: "bg-amber-500", red: "bg-red-500",
    blue: "bg-sky-500", gray: "bg-slate-400", "": "bg-slate-300",
  };
  var KPI_ACCENT = {
    green: "border-emerald-500", amber: "border-amber-500", red: "border-red-500",
    blue: "border-sky-500", gray: "border-slate-400", "": "border-slate-300",
  };
  var BAR = {
    green: "bg-emerald-500", amber: "bg-amber-500", red: "bg-red-500",
    blue: "bg-sky-500", gray: "bg-slate-400", "": "bg-slate-400",
  };

  // Registre des fiches ouvrables (réinitialisé à chaque rendu d'espace)
  var drawerStore = [];
  function regDrawer(d) { drawerStore.push(d); return drawerStore.length - 1; }

  function esc(s) {
    return String(s).replace(/[&<>"]/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" }[c];
    });
  }
  function badge(text, tone) {
    return '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ' +
      (TONE[tone] || TONE[""]) + '">' + esc(text) + "</span>";
  }
  function byId(id) { return espaces.filter(function (e) { return e.id === id; })[0]; }

  /* ---------- Sidebar & sélecteur de rôle ---------- */
  function buildNav() {
    var nav = document.getElementById("sidebar");
    var sel = document.getElementById("role-switch");
    nav.innerHTML = "";
    sel.innerHTML = "";
    espaces.forEach(function (e) {
      var a = document.createElement("a");
      a.href = "#/" + e.id;
      a.dataset.id = e.id;
      a.className = "flex items-center gap-3 px-5 py-2.5 hover:bg-slate-800/70 transition border-l-2 border-transparent";
      a.innerHTML = '<span class="text-base">' + e.icon + "</span>" +
        '<span class="flex-1">' + esc(e.label) + "</span>" +
        (e.tag ? '<span class="text-[9px] uppercase font-semibold bg-amber-400/20 text-amber-300 px-1.5 py-0.5 rounded">' + esc(e.tag) + "</span>" : "") +
        (e.vision ? '<span class="text-[9px] uppercase font-semibold bg-fuchsia-500/20 text-fuchsia-300 px-1.5 py-0.5 rounded">Vision</span>' : "");
      nav.appendChild(a);

      if (e.id !== "accueil") {
        var o = document.createElement("option");
        o.value = e.id; o.textContent = e.role + " — " + e.label;
        sel.appendChild(o);
      }
    });
    sel.addEventListener("change", function () { location.hash = "#/" + sel.value; });
  }

  /* ---------- Composants ---------- */
  function kpiCard(k) {
    return '<div class="bg-white rounded-xl shadow-sm border-l-4 ' + (KPI_ACCENT[k.tone] || KPI_ACCENT[""]) +
      ' p-4"><div class="text-xs text-slate-500">' + esc(k.label) + "</div>" +
      '<div class="text-2xl font-bold text-slate-900 mt-1">' + esc(k.value) + "</div>" +
      (k.sub ? '<div class="text-xs text-slate-400 mt-0.5">' + esc(k.sub) + "</div>" : "") + "</div>";
  }
  function cell(c) {
    if (c && typeof c === "object") return badge(c.b, c.t);
    return '<span class="text-slate-700">' + esc(c) + "</span>";
  }
  function tableBlock(b) {
    var hasDetail = b.rows.some(function (r) { return r && !Array.isArray(r) && r.detail; });
    var head = b.columns.map(function (c) {
      return '<th class="text-left font-semibold text-xs uppercase tracking-wide text-slate-400 px-4 py-2">' + esc(c) + "</th>";
    }).join("");
    var body = b.rows.map(function (r) {
      var cells = (r && !Array.isArray(r) && r.cells) ? r.cells : r;
      var detail = (r && !Array.isArray(r)) ? r.detail : null;
      var tds = cells.map(function (c) { return '<td class="px-4 py-2.5 text-sm border-t border-slate-100">' + cell(c) + "</td>"; }).join("");
      if (detail) {
        return '<tr data-drawer="' + regDrawer(detail) + '" class="hover:bg-brand-50 cursor-pointer">' + tds + "</tr>";
      }
      return "<tr class='hover:bg-slate-50'>" + tds + "</tr>";
    }).join("");
    var hint = hasDetail ? '<div class="text-xs text-brand-600 mb-2">→ Cliquez une ligne pour ouvrir la fiche détaillée</div>' : "";
    return card(b.title, hint +
      '<div class="overflow-x-auto"><table class="w-full"><thead><tr>' + head + "</tr></thead><tbody>" + body + "</tbody></table></div>");
  }

  /* Graphique en barres horizontales */
  function chartBlock(b) {
    var max = b.max || Math.max.apply(null, b.items.map(function (i) { return i.value; })) || 1;
    var rows = b.items.map(function (i) {
      var pct = Math.max(3, Math.round(i.value / max * 100));
      return '<div class="mb-2.5"><div class="flex justify-between text-xs mb-1">' +
        '<span class="text-slate-600">' + esc(i.label) + "</span>" +
        '<span class="text-slate-500 font-medium">' + esc(i.display != null ? i.display : i.value) + "</span></div>" +
        '<div class="h-2.5 rounded-full bg-slate-100"><div class="h-2.5 rounded-full ' + (BAR[i.tone] || BAR[""]) +
        '" style="width:' + pct + '%"></div></div></div>';
    }).join("");
    return card(b.title || "Statistiques", rows);
  }

  /* Kanban (colonnes de cartes) */
  function kanbanBlock(b) {
    var cols = b.columns.map(function (c) {
      var cards = (c.cards || []).map(function (k) {
        return '<div class="bg-white rounded-lg shadow-sm p-3 mb-2">' +
          '<div class="text-sm font-medium text-slate-800">' + esc(k.title) + "</div>" +
          (k.sub ? '<div class="text-xs text-slate-500 mt-0.5">' + esc(k.sub) + "</div>" : "") +
          (k.badge ? '<div class="mt-1.5">' + badge(k.badge.b, k.badge.t) + "</div>" : "") + "</div>";
      }).join("");
      return '<div class="w-56 shrink-0 bg-slate-100 rounded-lg p-2">' +
        '<div class="text-xs font-semibold text-slate-500 uppercase px-1 mb-2 flex items-center justify-between">' +
        '<span>' + esc(c.title) + '</span><span class="bg-white rounded-full px-1.5 text-slate-400">' + (c.cards ? c.cards.length : 0) + "</span></div>" +
        cards + "</div>";
    }).join("");
    return card(b.title || "Pipeline", '<div class="flex gap-3 overflow-x-auto pb-1">' + cols + "</div>");
  }

  /* Matrice (ex. permissions rôles × modules) */
  function matrixBlock(b) {
    var sym = { "true": '<span class="text-emerald-600 font-bold">✓</span>', "false": '<span class="text-slate-300">–</span>', "p": '<span class="text-amber-500">◐</span>' };
    var head = '<th class="text-left text-xs font-semibold text-slate-400 px-3 py-2"></th>' +
      b.columns.map(function (c) { return '<th class="text-center text-xs font-semibold text-slate-400 px-2 py-2">' + esc(c) + "</th>"; }).join("");
    var body = b.rows.map(function (r) {
      var tds = r.values.map(function (v) {
        var key = String(v);
        return '<td class="text-center px-2 py-2 border-t border-slate-100">' + (sym[key] != null ? sym[key] : esc(v)) + "</td>";
      }).join("");
      return '<tr class="hover:bg-slate-50"><td class="px-3 py-2 text-sm text-slate-700 border-t border-slate-100 font-medium">' + esc(r.label) + "</td>" + tds + "</tr>";
    }).join("");
    return card(b.title || "Matrice",
      '<div class="overflow-x-auto"><table class="w-full"><thead><tr>' + head + "</tr></thead><tbody>" + body + "</tbody></table></div>" +
      '<div class="text-xs text-slate-400 mt-2">' + (b.legend || "✓ autorisé · ◐ partiel · – aucun accès") + "</div>");
  }

  /* Grille (ex. présences apprenants × jours) ; cellule = "X" | {v,t} */
  function gridBlock(b) {
    var head = '<th class="text-left text-xs font-semibold text-slate-400 px-2 py-1"></th>' +
      b.columns.map(function (c) { return '<th class="text-center text-[10px] font-semibold text-slate-400 px-1 py-1">' + esc(c) + "</th>"; }).join("");
    var body = b.rows.map(function (r) {
      var tds = r.cells.map(function (c) {
        var t = (c && typeof c === "object") ? c.t : "";
        var v = (c && typeof c === "object") ? c.v : c;
        return '<td class="px-1 py-1 text-center"><span class="inline-block h-6 w-6 leading-6 rounded text-[11px] font-semibold ' + (TONE[t] || TONE[""]) + '">' + esc(v) + "</span></td>";
      }).join("");
      return '<tr><td class="px-2 py-1 text-sm text-slate-700 font-medium whitespace-nowrap">' + esc(r.label) + "</td>" + tds + "</tr>";
    }).join("");
    return card(b.title || "Grille",
      '<div class="overflow-x-auto"><table class="w-full"><thead><tr>' + head + "</tr></thead><tbody>" + body + "</tbody></table></div>" +
      (b.legend ? '<div class="text-xs text-slate-400 mt-2">' + esc(b.legend) + "</div>" : ""));
  }

  /* ---------- Tiroir de détail (fiche cliquable) ---------- */
  function openDrawer(idx) {
    var d = drawerStore[idx];
    if (!d) return;
    document.getElementById("drawer-title").textContent = d.title || "Détail";
    var tabsEl = document.getElementById("drawer-tabs");
    var bodyEl = document.getElementById("drawer-body");
    var tabs = d.tabs || [{ name: "Détail", blocks: d.blocks || [] }];
    tabsEl.innerHTML = tabs.map(function (t, i) {
      return '<button data-tab="' + i + '" class="px-3 py-2.5 text-sm border-b-2 ' +
        (i === 0 ? "border-brand-500 text-brand-700 font-semibold" : "border-transparent text-slate-500") + '">' + esc(t.name) + "</button>";
    }).join("");
    function show(i) {
      bodyEl.innerHTML = renderBlocks(tabs[i].blocks);
      bodyEl.scrollTop = 0;
      Array.prototype.forEach.call(tabsEl.children, function (btn, j) {
        var on = j === i;
        btn.classList.toggle("border-brand-500", on); btn.classList.toggle("text-brand-700", on);
        btn.classList.toggle("font-semibold", on);
        btn.classList.toggle("border-transparent", !on); btn.classList.toggle("text-slate-500", !on);
      });
    }
    tabsEl.onclick = function (e) { var b = e.target.closest("[data-tab]"); if (b) show(+b.dataset.tab); };
    show(0);
    document.getElementById("drawer-overlay").classList.remove("hidden");
    document.getElementById("drawer").classList.remove("translate-x-full");
  }
  function closeDrawer() {
    document.getElementById("drawer-overlay").classList.add("hidden");
    document.getElementById("drawer").classList.add("translate-x-full");
  }
  function listBlock(b) {
    var items = b.items.map(function (it) {
      return '<li class="flex items-start gap-3 py-2.5 border-t border-slate-100 first:border-0">' +
        '<span class="mt-1.5 h-2 w-2 rounded-full shrink-0 ' + (DOT[it.tone] || DOT[""]) + '"></span>' +
        '<div class="flex-1"><div class="text-sm text-slate-700">' + esc(it.text) + "</div>" +
        (it.meta ? '<div class="text-xs text-slate-400">' + esc(it.meta) + "</div>" : "") + "</div></li>";
    }).join("");
    return card(b.title, "<ul>" + items + "</ul>");
  }
  function checklistBlock(b) {
    var items = b.items.map(function (it) {
      return '<li class="flex items-center justify-between py-2.5 border-t border-slate-100 first:border-0">' +
        '<span class="flex items-center gap-3 text-sm text-slate-700">' +
        '<span class="h-2.5 w-2.5 rounded-full ' + (DOT[it.tone] || DOT[""]) + '"></span>' + esc(it.text) + "</span>" +
        badge(it.state, it.tone) + "</li>";
    }).join("");
    return card(b.title, "<ul>" + items + "</ul>");
  }
  function card(title, inner) {
    return '<section class="bg-white rounded-xl shadow-sm p-4 mb-5">' +
      '<h3 class="font-semibold text-slate-800 mb-2">' + esc(title) + "</h3>" + inner + "</section>";
  }

  /* Séparateur de module (titre + objectif) */
  function subheadingBlock(b) {
    return '<div class="mt-8 mb-4 flex items-start gap-3">' +
      '<span class="mt-0.5 h-6 w-1.5 rounded bg-brand-500 shrink-0"></span>' +
      '<div><h2 class="text-lg font-bold text-slate-900">' + (b.icon ? b.icon + " " : "") + esc(b.text) + "</h2>" +
      (b.note ? '<p class="text-sm text-slate-500 mt-0.5 max-w-3xl">' + esc(b.note) + "</p>" : "") + "</div></div>";
  }

  /* Fonctionnalités attendues (boutons non actifs) */
  function actionsBlock(b) {
    var btns = b.items.map(function (t) {
      return '<span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-50 text-sm text-slate-700 hover:bg-brand-50 hover:border-brand-200 cursor-default">' +
        '<span class="text-brand-500">+</span>' + esc(t) + "</span>";
    }).join(" ");
    return card(b.title || "Fonctionnalités", '<div class="flex flex-wrap gap-2">' + btns + "</div>");
  }

  /* Types / étiquettes neutres */
  function tagsBlock(b) {
    var tags = b.items.map(function (t) {
      return '<span class="inline-flex items-center px-2.5 py-1 rounded-md bg-slate-100 text-slate-600 text-xs">' + esc(t) + "</span>";
    }).join(" ");
    return card(b.title || "Types", '<div class="flex flex-wrap gap-2">' + tags + "</div>");
  }

  /* Machine à états : pipeline de statuts (statut courant mis en avant) */
  function statusesBlock(b) {
    var n = b.items.length;
    var chips = b.items.map(function (s, i) {
      var on = s === b.current;
      var cls = on
        ? "bg-brand-600 text-white border-brand-600 font-semibold"
        : "bg-white text-slate-500 border-slate-200";
      return '<div class="flex items-center">' +
        '<span class="px-2.5 py-1 rounded-full border text-xs ' + cls + '">' + esc(s) + "</span>" +
        (i < n - 1 ? '<span class="mx-1 text-slate-300">›</span>' : "") + "</div>";
    }).join("");
    return card(b.title || "Statuts possibles (machine à états)",
      '<div class="flex flex-wrap gap-y-2 items-center">' + chips + "</div>" +
      (b.current ? '<div class="text-xs text-slate-400 mt-2">Statut actuel de l\'exemple : <span class="text-slate-600 font-medium">' + esc(b.current) + "</span></div>" : ""));
  }

  /* Fiche détaillée : grille libellé / valeur */
  function fieldsBlock(b) {
    var rows = b.data.map(function (d) {
      var label = d[0], val = d[1];
      var valHtml = (val && typeof val === "object") ? badge(val.b, val.t) : '<span class="text-sm text-slate-800 font-medium">' + esc(val) + "</span>";
      return '<div class="py-2 border-b border-slate-100">' +
        '<div class="text-[11px] uppercase tracking-wide text-slate-400">' + esc(label) + "</div>" +
        '<div class="mt-0.5">' + valHtml + "</div></div>";
    }).join("");
    var inner = '<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6">' + rows + "</div>";
    return card(b.title || "Fiche", (b.subtitle ? '<p class="text-xs text-slate-500 -mt-1 mb-3">' + esc(b.subtitle) + "</p>" : "") + inner);
  }

  /* Règles métier (encadré) */
  function rulesBlock(b) {
    var items = b.items.map(function (t) {
      return '<li class="flex items-start gap-2 text-sm text-amber-900"><span class="mt-0.5">⚖️</span><span>' + esc(t) + "</span></li>";
    }).join("");
    return '<section class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-5">' +
      '<h3 class="font-semibold text-amber-900 mb-2">' + esc(b.title || "Règles métier") + "</h3>" +
      "<ul class='space-y-1.5'>" + items + "</ul></section>";
  }

  function renderBlocks(blocks) {
    return (blocks || []).map(function (b) {
      if (b.type === "table") return tableBlock(b);
      if (b.type === "list") return listBlock(b);
      if (b.type === "checklist") return checklistBlock(b);
      if (b.type === "subheading") return subheadingBlock(b);
      if (b.type === "actions") return actionsBlock(b);
      if (b.type === "tags") return tagsBlock(b);
      if (b.type === "statuses") return statusesBlock(b);
      if (b.type === "fields") return fieldsBlock(b);
      if (b.type === "rules") return rulesBlock(b);
      if (b.type === "chart") return chartBlock(b);
      if (b.type === "kanban") return kanbanBlock(b);
      if (b.type === "matrix") return matrixBlock(b);
      if (b.type === "grid") return gridBlock(b);
      return "";
    }).join("");
  }

  /* ---------- Page d'accueil ---------- */
  function renderAccueil(e) {
    // Bandeau de statistiques (dans le hero)
    var stats = (e.stats || []).map(function (s) {
      return '<div class="bg-white/10 rounded-xl px-4 py-3 backdrop-blur-sm">' +
        '<div class="text-2xl font-bold text-white">' + esc(s.value) + "</div>" +
        '<div class="text-xs text-brand-50">' + esc(s.label) + "</div></div>";
    }).join("");

    var hero = '<div class="bg-gradient-to-br from-brand-600 to-brand-800 text-white rounded-2xl p-8 mb-6 shadow-sm">' +
      '<div class="inline-flex items-center gap-2 bg-white/15 rounded-full px-3 py-1 text-xs font-medium mb-4">⚡ ERP CFA · plateforme unique</div>' +
      '<h2 class="text-3xl font-bold leading-tight max-w-3xl">' + esc(e.title) + "</h2>" +
      (e.tagline ? '<p class="mt-3 text-lg text-brand-50 max-w-3xl">' + esc(e.tagline) + "</p>" : "") +
      (e.intro ? '<p class="mt-2 text-sm text-brand-100/90 max-w-3xl">' + esc(e.intro) + "</p>" : "") +
      (stats ? '<div class="mt-6 grid grid-cols-2 sm:grid-cols-4 gap-3 max-w-3xl">' + stats + "</div>" : "") +
      "</div>";

    // Différenciateurs (piliers)
    var pillars = (e.pillars || []).map(function (p) {
      return '<div class="bg-white rounded-xl shadow-sm p-5 hover:shadow-md transition">' +
        '<div class="text-2xl">' + p.icon + "</div>" +
        '<div class="font-semibold text-slate-800 mt-2">' + esc(p.title) + "</div>" +
        '<div class="text-sm text-slate-500 mt-1">' + esc(p.text) + "</div></div>";
    }).join("");
    var pillarsSection = pillars ?
      '<div class="flex items-center justify-between mb-3 mt-2">' +
        '<h3 class="font-semibold text-slate-700">Ce qui nous distingue</h3>' +
        '<a href="#/intelligence" class="text-sm text-brand-600 hover:underline">Voir l\'espace Intelligence ⚡</a></div>' +
      '<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-7">' + pillars + "</div>" : "";

    // Parcours de bout en bout
    var chips = DATA.parcours.map(function (p, i) {
      return '<div class="flex items-center">' +
        '<span class="px-3 py-1.5 rounded-lg bg-slate-50 border border-slate-200 text-sm font-medium text-slate-700">' + esc(p) + "</span>" +
        (i < DATA.parcours.length - 1 ? '<span class="mx-1 text-brand-500">→</span>' : "") + "</div>";
    }).join("");
    var parcoursSection =
      '<h3 class="font-semibold text-slate-700 mb-3">Le parcours couvert de bout en bout</h3>' +
      '<div class="bg-white rounded-xl shadow-sm p-5 mb-7"><div class="flex flex-wrap gap-y-2 items-center">' + chips + "</div></div>";

    // Espaces (départements)
    var cards = espaces.filter(function (x) { return x.id !== "accueil"; }).map(function (x) {
      return '<a href="#/' + x.id + '" class="group bg-white rounded-xl shadow-sm p-5 hover:shadow-md hover:-translate-y-0.5 transition flex items-start gap-4">' +
        '<div class="h-11 w-11 rounded-lg bg-brand-50 grid place-items-center text-xl shrink-0">' + x.icon + "</div>" +
        '<div><div class="font-semibold text-slate-800 group-hover:text-brand-700 flex items-center gap-2 flex-wrap">' + esc(x.label) +
        (x.tag ? '<span class="text-[9px] uppercase font-semibold bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded">' + esc(x.tag) + "</span>" : "") +
        (x.vision ? '<span class="text-[9px] uppercase font-semibold bg-fuchsia-100 text-fuchsia-600 px-1.5 py-0.5 rounded">Vision</span>' : "") + "</div>" +
        '<div class="text-xs text-slate-500 mt-0.5">Rôle : ' + esc(x.role) + "</div></div></a>";
    }).join("");
    var cardsSection =
      '<h3 class="font-semibold text-slate-700 mb-3">Choisissez votre espace</h3>' +
      '<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">' + cards + "</div>";

    return hero + pillarsSection + parcoursSection + cardsSection;
  }

  /* ---------- Rendu d'un espace ---------- */
  function render(id) {
    drawerStore.length = 0;
    var e = byId(id) || byId("accueil");
    document.getElementById("page-title").textContent = e.label;
    var sel = document.getElementById("role-switch");
    if (e.id !== "accueil") sel.value = e.id;

    // surbrillance menu
    Array.prototype.forEach.call(document.querySelectorAll("#sidebar a"), function (a) {
      var on = a.dataset.id === e.id;
      a.classList.toggle("bg-slate-800/70", on);
      a.classList.toggle("border-brand-500", on);
      a.classList.toggle("text-white", on);
    });

    var view = document.getElementById("view");
    if (e.special === "accueil") { view.innerHTML = renderAccueil(e); window.scrollTo(0, 0); return; }

    var header =
      '<div class="mb-5">' +
      '<div class="flex items-center gap-3"><h2 class="text-xl font-bold text-slate-900">' + esc(e.title) + "</h2>" +
      (e.vision ? '<span class="text-[10px] uppercase font-semibold bg-fuchsia-100 text-fuchsia-600 px-2 py-0.5 rounded">Hors V1 — vision cible</span>' : "") + "</div>" +
      '<p class="text-sm text-slate-500 mt-1 max-w-3xl">' + esc(e.intro) + "</p></div>";

    var kpis = e.kpis ? '<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">' + e.kpis.map(kpiCard).join("") + "</div>" : "";

    view.innerHTML = header + kpis + renderBlocks(e.blocks);
    window.scrollTo(0, 0);
  }

  function route() {
    var id = (location.hash || "").replace(/^#\/?/, "") || "accueil";
    render(id);
  }

  // init
  document.querySelectorAll("[id]"); // no-op safety
  var foot = document.querySelector("aside .border-t");
  if (foot) foot.innerHTML = foot.innerHTML.replace("{{date}}", DATA.meta.date);
  buildNav();

  // Ouverture des fiches au clic + fermeture du tiroir
  document.getElementById("view").addEventListener("click", function (e) {
    var row = e.target.closest("[data-drawer]");
    if (row) openDrawer(+row.dataset.drawer);
  });
  document.getElementById("drawer-overlay").addEventListener("click", closeDrawer);
  document.getElementById("drawer-close").addEventListener("click", closeDrawer);
  document.addEventListener("keydown", function (e) { if (e.key === "Escape") closeDrawer(); });

  window.addEventListener("hashchange", function () { closeDrawer(); route(); });
  route();
})();
