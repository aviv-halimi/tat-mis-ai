var calendar;
var days = {};

$('.day').off('click').on('click', function(e) {
  var $o = $(this).closest('.card-body');
  $o.find('.slots').slideToggle(400);
});
$('.btn-po-event').off('click').on('click', function(e) {
  e.preventDefault();
  var $this = $(this);
  Swal.fire({
    title: 'Are you sure?',
    html: $this.data('title'),
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Yes, continue'
  }).then((result) => {
    if (result.value) {
      $('.btn-status').hide();
      $('#status_po').addClass('mb-2');
      postAjaxFunc('po-events', {id: $this.data('id')}, 'status_po', function(data) {
      }, function(data) { 
        $('.btn-status').show();
        if (data.click) {
          $(data.click).trigger('click');
        }
        else {       
          Swal.fire({
            icon: 'error',
            title: 'Something went wrong ...',
            text: data.response
          });
        }
      });
    }
  });
});


$(document).ready(function() {
	var calendar_default_view = Cookies.get('calendar_default_view');
	if (typeof calendar_default_view === 'undefined') {
		calendar_default_view = 'month';
	}
	  calendar = $('#calendar').fullCalendar({
      minTime: "06:00:00",
		defaultView: calendar_default_view,
        header: {
            left: 'prev,next today',
            center: 'title',
            right: 'month,agendaWeek,agendaDay,listMonth'
        },
        initialView: 'timelineWeek',
        themeSystem: 'bootstrap4',
        bootstrapFontAwesome: true,
        // customize the button names,
        // otherwise they'd all just say "list"
        views: {
            listDay: { buttonText: 'list day' },
            listWeek: { buttonText: 'list week' }
        },
		navLinks: true, // can click day/week names to navigate views
		selectable: true,
		selectHelper: true,
		eventClick: function(e, jsEvent, view) {
      showDialog(e.po_number, 'po-event', {c: e.po_code, a: e.vendor, b: e.start.format()});
		},
		eventRender: function(e, el) {
			if (e.icon) {          
				el.find(".fc-title").prepend("<i class='fa fa-"+e.icon+"'></i> ");
			}    
		},
		select: function(start, end) {
          showDialog('Add Excluded Days', 'po-event-settings', {_a: 'modal', __a: start.format()}, function() {},'', '');
		  //, function(data) {
			/*
			var eventData;
			eventData = {
			  title: data.title,
			  start: data.start,
			  end: data.end,
			  allDay: data.allDay
			};
			$('#calendar').fullCalendar('renderEvent', eventData, true); // stick? = true*/
		  //});
		  //$('#calendar').fullCalendar('unselect');
		  
		},
		eventClick: function(e, jsEvent, view) {
		  showDialog(e.title, 'po-events', {_a:'modal', calendar: 1, id: e.id}, function() {},'', '');
		  //$(this).css('border-color', 'red');    
		},
		editable: true,
		eventLimit: true, // allow "more" link when too many events
		events: function(start, end, timezone, callback) {
		  postAjax('scheduling', { "v": $('#_v').val(), "c": $('#_c').val(), "start": start.format("YYYY-MM-DD"), "end": end.format("YYYY-MM-DD") }, '', function(data) {
              days = data.days;  
			  callback(data.events);
		  });
		},
		viewRender: function(v, e) {
			Cookies.set('calendar_default_view', v.name);
        }
	  });
  
  });