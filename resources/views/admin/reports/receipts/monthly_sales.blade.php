{{-- begin: Entry --}}
<div class="row">
    <div class="col">
      <div class="card card-custom gutter-t">
        <div class="card-header">
          <div class="card-title">
            Monthly Sales
          </div>
        </div>
        <div class="card-body">
          <div class="row">
            <div class="col-6">
              <div class="form-group">
                <label for="">Select Year</label>
                  <select class="form-control" id="year_search" name="param">
                </select>
              </div>
            </div>
            <div class="col-6">
              <div class="d-flex justify-content-end">
                <div class="btn-group">
                  <button type="button" class="btn btn-secondary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    Tools
                  <span class="sr-only">Toggle Dropdown</span>
                  </button>
                  <div class="dropdown-menu dropdown-menu-right">
                      <a href="" id="export_print_for_ms" class="dropdown-item">Print</a>
                      <a href="" id="export_copy_for_ms" class="dropdown-item">Copy</a>
                      <a href="" id="export_excel_for_ms" class="dropdown-item">Excel</a>
                      <a href="" id="export_csv_for_ms" class="dropdown-item">CSV</a>
                      <a href="" id="export_pdf_for_ms" class="dropdown-item">PDF</a>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-lg-7">
              <div id="chart_1"></div>
            </div>
            <div class="col-lg-5">
              <table class="table table-hover" id="tblMonthlySales">
                <thead>
                  <tr>
                    <th>Month</th>
                    <th>Gross</th>
                    <th>Refund</th>
                    <th>Net</th>
                    <th>Revenue</th>
                  </tr>
                </thead>
                <tbody>
                  
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
 <!--end::Entry-->


 @section('script')
     <script>
         $(function(){
            loadYearDetails();
            $('#year_search').select2({
                placeholder: "Select year",
                allowClear: true,
            });
         })
      $optionsTblMonthlySales = {
      responsive: true,
      serverside: true,
      processing: true,
      "scrollY": "300px",
      "scrollCollapse": true,
      "lengthChange": false,
      "autoWidth": false,
      buttons: [
          'print',
          'copyHtml5',
          'excelHtml5',
          'csvHtml5',
          'pdfHtml5',
      ],
      columns: [
        {"data": "x"},
        {"data": "gross"},
        {"data": "refund"},
        {"data": "net"},
        {"data": "revenue"},
      ],
      ajax: {
        data:{
            'date': function() { return $year},
            'user': '{{auth()->user()->user_id}}'
        },
        url: "{{route('api.sales-monthly-table')}}",
      },
    };
    $optionsTblMonthlySalesBIR = {
      responsive: true,
      serverside: true,
      processing: true,
      scrollY: "300px",
      "scrollCollapse": true,
      "lengthChange": false,
      "autoWidth": false,
      buttons: [
          'print',
          'copyHtml5',
          'excelHtml5',
          'csvHtml5',
          'pdfHtml5',
      ],
      columns: [
        {"data": "x"},
        {"data": "gross"},
        {"data": "refund"},
        {"data": "net"},
        {"data": "revenue"},
      ],
      ajax: {
        data:{
            'date': function() { return $year},
            'user': '{{auth()->user()->user_id}}'
        },
        url: "{{route('api.bir-sales-monthly')}}",
      },
    };

    function loadTblMonthlySales(){
      var table = $("#tblMonthlySales").DataTable($optionsTblMonthlySales);
      $('#export_print_for_ms').on('click', function(e) {
          e.preventDefault();
          table.button(0).trigger();
      });

      $('#export_copy_for_ms').on('click', function(e) {
          e.preventDefault();
          table.button(1).trigger();
      });

      $('#export_excel_for_ms').on('click', function(e) {
          e.preventDefault();
          table.button(2).trigger();
      });

      $('#export_csv_for_ms').on('click', function(e) {
          e.preventDefault();
          table.button(3).trigger();
      });

      $('#export_pdf_for_ms').on('click', function(e) {
          e.preventDefault();
          table.button(4).trigger();
      });
    }
      // Function for Reinializing Apex Chart for monthly Sales
    function reloadYears(){
      // console.log(this.$year);
      $.ajax({
        url: "{{route('api.sales-yearly')}}",
        data: {
          'date': function(){return $year},
          'user':{{auth()->user()->user_id}}
        },
        success: function(data){
          chartLine.updateSeries([{
            data: data.output
          }])
        },
        error: function(data){
          console.log(data);
        }
      });
    }
     </script>
 @endsection