$(document).ready(function() {
    let titleIdentifiers = 'Advertisement';
    let url = '/admin/advertisements';
    let table = $('#advertisementTable');

    // Initialize
    init();

    function init() {
        const options = {
            filter: true,
            responsive: true,
            serverside: true,
            processing: true,
            order: [[4, 'asc']], // Order by display_order
            buttons: [
                'copyHtml5',
                'excelHtml5',
                'csvHtml5',
                'pdfHtml5',
            ],
            ajax: {
                url: "advertisements/table",
                dataSrc: function(response) {
                    // Update counters
                    let activeCount = 0;
                    let inactiveCount = 0;
                    response.data.forEach(item => {
                        if (item.status) {
                            activeCount++;
                        } else {
                            inactiveCount++;
                        }
                    });
                    $('#activeCount').text(activeCount);
                    $('#inactiveCount').text(inactiveCount);

                    return response.data;
                }
            },
            columns: [
                {'data': 'preview'},
                {'data': 'name'},
                {'data': 'type_badge'},
                {'data': 'duration_formatted'},
                {'data': 'display_order'},
                {'data': 'status_badge'},
                {'data': 'actions'}
            ],
            columnDefs: [
                {
                    targets: 0,
                    orderable: false,
                    className: 'text-center'
                },
                {
                    targets: 1,
                    render: function (data, type, full) {
                        let description = full.description || '';
                        if (description.length > 50) {
                            description = description.substring(0, 50) + '...';
                        }

                        return `
                            <div class="d-flex flex-column">
                                <span class="fw-bold fs-6">${data}</span>
                                ${description ? `<span class="text-muted fs-7">${description}</span>` : ''}
                            </div>
                        `;
                    }
                },
                {
                    targets: 2,
                    className: 'text-center',
                    orderable: false
                },
                {
                    targets: 3,
                    className: 'text-center',
                    render: function (data, type, full) {
                        const icon = full.media_type === 'video' ? 'fa-video' : 'fa-image';
                        return `<span class="badge badge-light-dark"><i class="fas ${icon} me-1"></i>${data}</span>`;
                    }
                },
                {
                    targets: 4,
                    className: 'text-center',
                    render: function (data) {
                        return `<span class="badge badge-light-primary">#${data}</span>`;
                    }
                },
                {
                    targets: 5,
                    className: 'text-center',
                    orderable: false
                },
                {
                    targets: 6,
                    className: 'text-end',
                    orderable: false
                }
            ]
        };

        const dataTable = table.DataTable(options);

        // Handle Delete
        handleDelete(
            titleIdentifiers,
            table,
            $('#delete' + titleIdentifiers + 'Modal'),
            document.querySelector('#btnDelete' + titleIdentifiers),
            document.querySelector('#delete' + titleIdentifiers + 'Form'),
            url
        );
    }
});
