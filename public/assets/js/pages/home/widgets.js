function homeCardChartWidget (chartHeight, color) {
    return {
        grid: {
            padding: {
                left: 0,
                top: 0,
                right: 0,
                bottom: 0
            }
        },
        chart: {
            fontFamily: 'inherit',
            height: chartHeight,
            toolbar: {
                show: false
            },
            sparkline: {
                enabled: true
            }
        },
        plotOptions: {

        },
        legend: {
            show: false
        },
        dataLabels: {
            enabled: false
        },
        stroke: {
            curve: 'smooth',
            show: true,
            width: 2,
            colors: [color]
        },
        xaxis: {
            categories: [],
            labels: {
                show: false
            },
            axisBorder: {
                show: false
            },
            axisTicks: {
                show: false,
            }
        },
        yaxis: {
            labels: {
                show: false
            },
        },
        fill: {
            opacity: 1,
        },
        states: {
            normal: {
                filter: {
                    type: 'none',
                    value: 0
                }
            },
            hover: {
                filter: {
                    type: 'none',
                    value: 0
                }
            },
            active: {
                allowMultipleDataPointsSelection: false,
                filter: {
                    type: 'none',
                    value: 0
                }
            }
        },
        tooltip: {
            style: {
                fontSize: '12px'
            },
            y: {
                formatter: function (val) {
                    return numberWithCommas(val)
                }
            }
        },
        colors: [color],
        grid: {
            show: false,
            padding: {
                top: 0,
                bottom: 0,
            },
            xaxis: {
                lines: {
                    show: false,
                },
            },
            yaxis: {
                lines: {
                    show: false,
                },
            },
        },
        series: [],
        noData: {
            text: 'Loading...'
        }
    }
}

function numberWithCommas(x) {
    return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}