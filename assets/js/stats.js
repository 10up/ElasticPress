/* eslint-disable no-new */

import Chart from 'chart.js';

const { epChartData } = window;

/**
 * Generates a random string representing a color.
 *
 * @returns {string} Random color
 */
function getRandomColor() {
	const letters = '0123456789ABCDEF';
	let color = '#';

	for (let i = 0; i < 6; i += 1) {
		color += letters[Math.floor(Math.random() * 16)];
	}

	return color;
}

const barData = Object.entries(epChartData.indices_data);
const barLabels = [];
const barDocs = [];
const barColors = [];

Chart.defaults.global.legend.labels.usePointStyle = true;

barData.forEach(function (data) {
	barLabels.push(data[1].name);
	barDocs.push(data[1].docs);
	barColors.push(getRandomColor());
});

const documentChart = document.getElementById('documentChart');
if (documentChart) {
	new Chart(documentChart, {
		type: 'horizontalBar',
		data: {
			labels: barLabels,
			datasets: [
				{
					label: 'Documents',
					backgroundColor: barColors,
					data: barDocs,
				},
			],
		},
		options: {
			legend: {
				display: false,
			},
			title: {
				display: true,
			},
		},
	});
}

const queriesTotalChart = document.getElementById('queriesTotalChart');
if (queriesTotalChart) {
	new Chart(document.getElementById('queriesTotalChart'), {
		type: 'pie',
		data: {
			labels: ['Indexing operations', 'Total Query operations'],
			datasets: [
				{
					label: '',
					backgroundColor: ['#5ba9a7', '#2e7875', '#a980a4'],
					data: [epChartData.index_total, epChartData.query_total],
				},
			],
		},
		options: {
			responsive: false,
			title: {
				display: true,
			},
			legend: {
				position: 'right',
			},
			tooltips: {
				callbacks: {
					/**
					 * Appends the string operations before tooltip value
					 *
					 * @param {object} item Chat item
					 * @param {object} data Data
					 * @returns {string} Operations
					 */
					label(item, data) {
						const dataset = data.datasets[item.datasetIndex];
						const currentValue = dataset.data[item.index];

						return `Operations: ${currentValue}`;
					},
				},
			},
		},
	});
}
