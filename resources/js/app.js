import './bootstrap';

import Alpine from 'alpinejs';
import { createApp } from 'vue';
import CurriculumBuilder from './components/CurriculumBuilder.vue';

window.Alpine = Alpine;

Alpine.start();

const curriculumRoot = document.getElementById('curriculum-builder-root');
const curriculumConfigEl = document.getElementById('curriculum-builder-config');

if (curriculumRoot && curriculumConfigEl) {
	try {
		const config = JSON.parse(curriculumConfigEl.textContent || '{}');
		createApp(CurriculumBuilder, config).mount(curriculumRoot);
	} catch (error) {
		// Keep the page usable even if parsing/mounting fails.
		// eslint-disable-next-line no-console
		console.error('Failed to mount curriculum builder:', error);
	}
}
