'use strict'

$(document).ready(function () {
    //date('Y-m-d', $dateFrom)
    moment.updateLocale('en', {
        months: [ 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre' ],
        monthsShort : [ 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic' ]
    });
    
    var start = moment().subtract(29, 'days');
    var end = moment();

    function cb(start, end) {
        $('#reportrange span').html(start.format('D') + ' de ' + start.format('MMMM, YYYY') + ' - ' + end.format('D') + ' de ' + end.format('MMMM, YYYY'));
        
        $('input[name="sqlFrom"]').val(start.format('YYYY-MM-DD'));
        $('input[name="sqlTo"]').val(end.format('YYYY-MM-DD'));
        $('input[name="filenameFrom"]').val(start.format('D MMM YY'));
        $('input[name="filenameTo"]').val(end.format('D MMM YY'));
        $('input[name="headerFrom"]').val(start.format('DD/MM/YYYY'));
        $('input[name="headerTo"]').val(end.format('DD/MM/YYYY'));
    }

    $('#reportrange').daterangepicker({
        startDate: start,
        endDate: end,
        ranges: {
            'Hoy': [moment(), moment()],
            'Ayer': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
            'Últimos 7 Días': [moment().subtract(6, 'days'), moment()],
            'Últimos 30 Días': [moment().subtract(29, 'days'), moment()],
            'Este Mes': [moment().startOf('month'), moment().endOf('month')],
            'Último Mes': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
            'Este Año': [moment().startOf('year'), moment().endOf('year')],
            'Último Año': [moment().subtract(1, 'year').startOf('year'), moment().subtract(1, 'year').endOf('year')]
        },
        locale: {
            format: 'DD/MM/YYYY',
            applyLabel: 'Aplicar',
            cancelLabel: 'Cancelar',
            fromLabel: 'Desde',
            toLabel: 'Hasta',
            customRangeLabel: 'Personalizado',
            daysOfWeek: [ 'Do', 'Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sá' ],
            monthNames: [ 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre' ],
            firstDay: 1
        }
    }, cb);

    cb(start, end);

});