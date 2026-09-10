var options = {
	chart: {
		height: 317,
		type: "area",
		toolbar: {
			show: false,
		},
	},
	dataLabels: {
		enabled: false,
	},
	stroke: {
		curve: "smooth",
		width: 3,
	},
	forecastDataPoints: {
		count: 7,
	},
	series: [
		{
			name: "Revenue",
			// monthly revenue in USD
			data: [86990, 92000, 98000, 103000, 110000, 115000, 120000, 125000, 130000, 135000, 140000, 145000],
		},
		{
			name: "Orders",
			// monthly order count
			data: [500, 520, 540, 560, 580, 600, 620, 640, 660, 680, 700, 720],
		},
	],
	grid: {
		borderColor: "#e0e6ed",
		strokeDashArray: 5,
		xaxis: {
			lines: {
				show: true,
			},
		},
		yaxis: {
			lines: {
				show: false,
			},
		},
		padding: {
			top: 0,
			right: 0,
			bottom: 10,
			left: 0,
		},
	},
	xaxis: {
		categories: [
			"Jan",
			"Feb",
			"Mar",
			"Apr",
			"May",
			"Jun",
			"Jul",
			"Aug",
			"Sep",
			"Oct",
			"Nov",
			"Dec",
		],
	},
	yaxis: {
		labels: {
			show: true,
			formatter: function(val) { return val; }
		},
	},
	colors: ["#3659cd", "#a5acc3"],
	colors: ["#3659cd", "#a5acc3"],
	markers: {
		size: 0,
		opacity: 0.3,
		colors: ["#3659cd", "#a5acc3"],
		strokeColor: "#ffffff",
		strokeWidth: 2,
		hover: {
			size: 7,
		},
	},
};

var chart = new ApexCharts(document.querySelector("#overview"), options);

chart.render();
