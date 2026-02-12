var table;
var table2;
var table3;
var log_table;
var manual_entries_timeout;

$(function() {


    // Override defaults
    // ------------------------------

    // Setting datatable defaults
    $.extend( $.fn.dataTable.defaults, {
        buttons: [
          'copy',
          'excel',
          'csv',
          'pdf',
          { extend: 'print',  exportOptions: { modifier: { page: 'all', search: 'none' } } }
        ],
        dom: '<"datatable-header"<"row"<"col-sm-7"<"pl-2 pt-2"l><"float-left"B>><"col-sm-5"<"pr-2 pt-2"f>>>><"datatable-scroll"t><"datatable-footer"ip>',
        autoWidth: false,
        responsive: true,
        order: [[ 0, "desc" ]],
        stateSave: true,
        aLengthMenu : [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        iDisplayLength : 25,
        // dom: '<"datatable-header"fTl><"datatable-scroll"t><"datatable-footer"ip>',
            processing : true,
          /*language: {
              search: '<span>Filter:</span> _INPUT_',
              lengthMenu: '<span>Show:</span> _MENU_',
              paginate: { 'first': 'First', 'last': 'Last', 'next': '→', 'previous': '←' },
                          processing: '<i class="fa fa-spin fa-spinner"></i> Just a sec … updating records',
                          loadingRecords: '<i class="fa fa-spin fa-spinner"></i> Just a sec … loading records'
          },*/
        language: {
            searchPlaceholder: 'Quick Search...',
            sSearch: '',
            //lengthMenu: '<div style="display:inline-block;padding-right:10px;width:auto;">_MENU_</div> items/page',
            //paginate: { 'first': 'First', 'last': 'Last', 'next': '→', 'previous': '←' },
            processing: '<i class="fa fa-spin fa-spinner"></i> Just a sec … updating records',
            loadingRecords: '<i class="fa fa-spin fa-spinner"></i> Just a sec … loading records'
          },
          drawCallback: function () {
              //$(this).find('tbody tr').slice(-3).find('.dropdown, .btn-group').addClass('dropup');
              initAssets(false);
          },
          preDrawCallback: function() {
              //$(this).find('tbody tr').slice(-3).find('.dropdown, .btn-group').removeClass('dropup');
          }
      });
		/*
    // Define default path for DataTables SWF file
    $.fn.dataTable.TableTools.defaults.sSwfPath = "assets/swf/datatables/copy_csv_xls_pdf.swf"


    // Tabletools defaults
    $.extend(true, $.fn.DataTable.TableTools.classes, {
        "container" : "btn-group DTTT_container", // buttons container
        "buttons" : {
            "normal" : "btn btn-default", // default button classes
            "disabled" : "disabled" // disabled button classes
        },
        "collection" : {
            "container" : "dropdown-menu" // collection container to take dropdown menu styling
        },
        "select" : {
            "row" : "success" // selected row class
        }
    });
    */
	

/*
    // Collection dropdown defaults
    $.extend(true, $.fn.DataTable.TableTools.DEFAULTS.oTags, {
        collection: {
            container: "ul",
            button: "li",
            liner: "a"
        }
    });


*/
    // Table setup
    // ------------------------------



    // begin first table
    datatable = $('.datatable-live').each(function() {
        var $this = $(this);
        var data = { _a: 'list'};
        if ($this.data('a')) data['a'] = $this.data('a');
        if ($this.data('b')) data['b'] = $this.data('b');
        if ($this.data('c')) data['c'] = $this.data('c');
        var table = $this.DataTable({
            serverSide: $this.hasClass('large'),
            ajax: {
                url: '/' + $this.data('url'),
                type: 'post',
                data: data
            }
        });
    });



    $('.datatable').each(function() {
        $(this).DataTable({
            serverSide: $(this).hasClass('large'),
            ajax: {
                url: '/' + $('#PageName').val(),
                type: 'post',
                data: { _a:'list'}
            }
        });
    });

    $('.t-search-results').DataTable({});




	/* table mangager */
	
	$('.ckeditor-gallery').on('click', function(e) {
		e.preventDefault();
		var id = $(this).attr('id').replace('ckeditor_gallery_', '');
		$('.modal-title').text('Insert Image from Library');
		loadModalGallery(id);
    });
    /*
    $('.datepicker').datepicker({
        dateFormat: 'dd/mm/yy'
    });*/
                                   
        //app._loading.show($(".dtable"),{spinner: true});
        //app._loading.show($(".dtable-sm"),{spinner: true});

        $('.dtable-sm').DataTable({
            "searching": false,
            "paging": false, 
            "info": false,         
            "lengthChange":false,
            "responsive": true,
            "initComplete": function(settings, json) {
                setTimeout(function(){
                    //app._loading.hide($(".dtable-sm"));
                    initAssets();
                },1000);
            }
        });
        $('.dtable').DataTable({
          iDisplayLength : 10
        });

        log_table = $('.table-log').DataTable({
            iDisplayLength : 10
          });
     

        $('.table-module').DataTable({
            order: [[ 0, "asc" ]],
            "searching": true,
            "paging": true, 
            "info": true,         
            "lengthChange":true,
            "responsive": true
        });

        $('.table-analytics').DataTable({
          buttons: [
            'copy',
            { extend: 'excel',  exportOptions: { modifier: { page: 'all', search: 'none' } } },
            'csv',
            'pdf',
            { extend: 'print',  exportOptions: { modifier: { page: 'all', search: 'none' } } }
          ],
            //dom: '<"datatable-header"<"pl-2"l><"float-right pb-2 pr-2"B>><"datatable-scroll"t><"datatable-footer"ip>',
            dom: '<"datatable-header"<"row"<"col-sm-7"<"pl-2 pt-2"l><"float-left"B>><"col-sm-5"<"pr-2 pt-2"f>>>><"datatable-scroll"t><"datatable-footer"ip>',
            order: [[ 0, "asc" ]],
            "searching": true,
            "paging": true, 
            "info": true,         
            "lengthChange":true,
            "responsive": true
        });
        $('.table-export').DataTable({
          buttons: [
            'copy',
            { extend: 'excel',  exportOptions: { modifier: { page: 'all', search: 'none' } } },
            'csv',
            'pdf',
            { extend: 'print',  exportOptions: { modifier: { page: 'all', search: 'none' } } }
          ],
            //dom: '<"datatable-header"<"pl-2"l><"float-right pb-2 pr-2"B>><"datatable-scroll"t><"datatable-footer"ip>',
            dom: '<"datatable-header"<"p-2 mb-2"B>><"datatable-scroll"t><"datatable-footer"ip>',
            order: [[ 0, "asc" ]],
            "searching": false,
            "paging": false, 
            "info": false,         
            "lengthChange":false,
            "responsive": true
        });
        /*

        var table = $(".dtable").DataTable({
            "responsive": true,
            buttons: [ 'copy', 'excel', 'pdf', 'print', 'colvis' ],
            "initComplete": function(settings, json) {
                setTimeout(function(){
                    //app._loading.hide($(".dtable"));
                    initAssets();
                },1000);
            }
        });
 
     table.buttons().container().appendTo( '#example_wrapper .col-md-6:eq(0)' );*/
      

	
    // External table additions
    // ------------------------------

    // Add placeholder to the datatable filter option
    $('.dataTables_filter input[type=search]').attr('placeholder','Type to filter...');
    $('.dataTables_length select').addClass('form-control select2 m-r-2');
    $('.dataTables_filter label').addClass('inline');
    if (!$('.dataTables_filter .btn').length && $('#AddFileCode').length) {
        $('.dataTables_filter').prepend('<button type="button" class="btn btn-primary btn-dialog" data-url="file" data-title="Add File / Note" data-a="' + $('#AddFileTableName').val() + '" data-b="' + $('#AddFileCode').val() + '">Add File / Note</a>');
    }
    if (!$('.dataTables_filter .btn').length && $('#ModalUrl').length) $('.dataTables_filter').prepend('<button type="button" class="btn btn-primary btn-dialog" data-url="' + $('#ModalUrl').val() + '" data-title="Add New ' + $('#PageTitle').val() + '">Add New</a>');

    if (!$('.dataTables_filter .btn').length && $('#AddUrl').length) $('.dataTables_filter').prepend('<a href="' + $('#AddUrl').val() + '" class="btn btn-primary">Add New</a>');

    else if (!$('.dataTables_filter .btn').length && $('#AllowAdd').val() == 1) $('.dataTables_filter').prepend('<button type="button" class="btn btn-primary btn-add-dialog" data-url="' + $('#PageName').val() + '" data-title="Add New ' + $('#PageTitle').val() + '">Add New</a>');


    if ($('.table-log').length) {
        $.fn.dataTable.ext.search.push(
            function(settings, data, dataIndex) {
                return $(log_table.row(dataIndex).node()).attr('data-auto') == '0';
            }
        );
        log_table.draw();
    }


    $('.show-manual-entries-po').off('click').on('click', function(e) {
        var $this = $('#show_manual_entries_po');
        console.log($this.prop('checked'));
        clearTimeout(manual_entries_timeout);
        manual_entries_timeout = setTimeout(function() {
            if ($this.prop('checked')) {
                $.fn.dataTable.ext.search.push(
                    function(settings, data, dataIndex) {
                        return $(log_table.row(dataIndex).node()).attr('data-auto') == '0';
                    }
                );
                log_table.draw();
            }
            else {
                $.fn.dataTable.ext.search.pop();
                log_table.draw();
            }
        }, 100);
    });

});