// Local FullCalendar locale: French (fr)
// Loaded after FullCalendar's index.global.min.js
(function () {
  'use strict';

  if (typeof FullCalendar === 'undefined') return;
  FullCalendar.globalLocales = FullCalendar.globalLocales || [];

  FullCalendar.globalLocales.push(function () {
    var fr = {
      code: "fr",
      week: {
        dow: 1,
        doy: 4
      },
      buttonText: {
        prev: "Précédent",
        next: "Suivant",
        today: "Aujourd'hui",
        year: "Année",
        month: "Mois",
        week: "Semaine",
        day: "Jour",
        list: "Planning"
      },
      weekText: "Sem.",
      allDayText: "Toute la journée",
      moreLinkText: "en plus",
      noEventsText: "Aucun événement à afficher"
    };

    return fr;
  }());
}());
