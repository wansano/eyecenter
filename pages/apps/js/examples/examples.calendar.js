/*
Name: 			Pages / Calendar - Examples
Written by: 	Okler Themes - (http://www.okler.net)
Theme Version:  4.1.0
*/

(function($) {

	'use strict';

	var initCalendar = function() {

		  var calendarEl = document.getElementById('calendar');

		  var calendar = new FullCalendar.Calendar(calendarEl, {
		    initialView: 'dayGridMonth',
		    initialDate: '2024-10-01',
		    headerToolbar: {
		      left: 'prev,next today',
		      center: 'title',
		      right: 'dayGridMonth,timeGridWeek,timeGridDay'
		    },
		    events: [
		      {
		        title: 'Lunch',
		        start: '2024-01-12T12:00:00'
		      },
		      {
		        title: 'Meeting',
		        start: '2024-11-12T14:30:00'
		      },
		      {
		        title: 'Birthday Party',
		        start: '2024-11-03T07:00:00'
		      },
		      {
		        title: 'Click for Google',
		        url: 'http://google.com/',
		        start: '2024-10-29'
		      }
		    ]
		  });

		  calendar.render();

	};

	$(function() {
		initCalendar();
	});

}).apply(this, [jQuery]);