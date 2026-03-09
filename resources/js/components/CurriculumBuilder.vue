<template>
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Interactive Curriculum Builder</h3>
                        <p class="text-xs text-gray-500">Drag sections and lessons. Edit titles inline and auto-save changes.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50" @click="triggerImport">
                            Import JSON
                        </button>
                        <button type="button" class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50" @click="exportCurriculum">
                            Export JSON
                        </button>
                        <button type="button" class="rounded-md bg-indigo-600 px-3 py-2 text-sm text-white hover:bg-indigo-700" @click="addSection">
                            + Add Section
                        </button>
                    </div>
                </div>
                <div class="mt-3 flex items-center gap-3 text-xs">
                    <span class="text-gray-500">Auto-save: <strong :class="statusClass">{{ statusText }}</strong></span>
                    <label class="inline-flex items-center gap-2 text-gray-600">
                        Import mode
                        <select v-model="importMode" class="rounded border-gray-300 text-xs">
                            <option value="append">Append</option>
                            <option value="replace">Replace</option>
                        </select>
                    </label>
                    <input ref="importInput" type="file" class="hidden" accept="application/json" @change="importCurriculum">
                </div>
            </div>

            <div class="space-y-3">
                <article
                    v-for="(section, sectionIndex) in sectionList"
                    :key="section.id"
                    class="rounded-xl border border-gray-200 bg-gray-50 p-4"
                    draggable="true"
                    @dragstart="onSectionDragStart(sectionIndex, $event)"
                    @dragover.prevent
                    @drop="onSectionDrop(sectionIndex, $event)"
                >
                    <div class="flex items-start gap-2">
                        <button type="button" class="mt-2 cursor-move rounded border border-gray-300 bg-white px-2 py-1 text-xs text-gray-600" title="Drag section">
                            Drag
                        </button>
                        <div class="flex-1 space-y-2">
                            <div class="flex items-center gap-2">
                                <input
                                    v-model="section.title"
                                    type="text"
                                    class="w-full rounded-md border-gray-300 text-sm"
                                    placeholder="Section title"
                                    @input="scheduleAutosave"
                                >
                                <button type="button" class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-600 hover:bg-white" @click="toggleSection(section)">
                                    {{ section.collapsed ? 'Expand' : 'Collapse' }}
                                </button>
                                <button type="button" class="rounded border border-red-200 bg-red-50 px-2 py-1 text-xs text-red-700 hover:bg-red-100" @click="deleteSection(section)">
                                    Delete
                                </button>
                            </div>
                            <textarea
                                v-model="section.description"
                                rows="2"
                                class="w-full rounded-md border-gray-300 text-xs"
                                placeholder="Optional section description"
                                @input="scheduleAutosave"
                            />
                        </div>
                    </div>

                    <div v-show="!section.collapsed" class="mt-3 space-y-2">
                        <div class="flex items-center justify-between">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-600">Lessons</p>
                            <div class="flex items-center gap-2">
                                <select v-model="section.newLessonType" class="rounded border-gray-300 text-xs">
                                    <option v-for="type in lessonTypes" :key="type" :value="type">{{ type }}</option>
                                </select>
                                <button type="button" class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-white" @click="addLesson(section)">
                                    + Add Lesson
                                </button>
                            </div>
                        </div>

                        <div class="space-y-2 rounded-lg border border-dashed border-gray-300 p-2" @dragover.prevent @drop="onLessonDrop(sectionIndex, null, $event)">
                            <p v-if="!section.lessons.length" class="px-2 py-3 text-center text-xs text-gray-500">Drop lessons here or add new lessons.</p>

                            <div
                                v-for="(lesson, lessonIndex) in section.lessons"
                                :key="lesson.id"
                                class="rounded-md border border-gray-200 bg-white px-3 py-2"
                                draggable="true"
                                @dragstart.stop="onLessonDragStart(sectionIndex, lessonIndex, $event)"
                                @dragover.prevent
                                @drop.stop="onLessonDrop(sectionIndex, lessonIndex, $event)"
                            >
                                <div class="grid gap-2 md:grid-cols-12 md:items-center">
                                    <div class="md:col-span-5">
                                        <input
                                            v-model="lesson.title"
                                            type="text"
                                            class="w-full rounded border-gray-300 text-sm"
                                            placeholder="Lesson title"
                                            @input="scheduleAutosave"
                                        >
                                    </div>
                                    <div class="md:col-span-2">
                                        <select v-model="lesson.type" class="w-full rounded border-gray-300 text-xs" @change="scheduleAutosave">
                                            <option v-for="type in lessonTypes" :key="type" :value="type">{{ type }}</option>
                                        </select>
                                    </div>
                                    <div class="md:col-span-2">
                                        <input
                                            v-model.number="lesson.duration_minutes"
                                            type="number"
                                            min="0"
                                            class="w-full rounded border-gray-300 text-xs"
                                            placeholder="Minutes"
                                            @input="scheduleAutosave"
                                        >
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="inline-flex items-center gap-1 text-xs text-gray-600">
                                            <input v-model="lesson.is_free_preview" type="checkbox" @change="scheduleAutosave">
                                            Free Preview
                                        </label>
                                    </div>
                                    <div class="text-right md:col-span-1">
                                        <button type="button" class="rounded border border-gray-200 px-2 py-1 text-[11px] text-gray-500" title="Drag lesson">
                                            Drag
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </article>

                <p v-if="!sectionList.length" class="rounded-lg border border-dashed border-gray-300 bg-white p-6 text-center text-sm text-gray-500">
                    No sections yet. Click "Add Section" to start building your curriculum.
                </p>
            </div>
        </div>

        <aside class="space-y-6">
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <h4 class="text-sm font-semibold text-gray-900">Student Preview</h4>
                <p class="mt-1 text-xs text-gray-500">How your curriculum structure appears to students.</p>
                <div class="mt-3 max-h-[28rem] space-y-2 overflow-auto pr-1">
                    <div v-for="section in sectionList" :key="`preview-${section.id}`" class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                        <p class="text-xs font-semibold text-gray-700">{{ section.title }}</p>
                        <ul class="mt-1 space-y-1 text-xs text-gray-600">
                            <li v-for="lesson in section.lessons" :key="`preview-lesson-${lesson.id}`" class="flex items-center justify-between gap-2 rounded bg-white px-2 py-1">
                                <span class="truncate">{{ lesson.title }}</span>
                                <span class="shrink-0 text-[10px] uppercase text-gray-400">{{ lesson.type }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
                <a :href="previewUrl" target="_blank" class="mt-3 inline-flex items-center rounded-md border border-gray-300 px-3 py-2 text-xs text-gray-700 hover:bg-gray-50">
                    Open Full Preview
                </a>
            </div>

            <form :action="continueUrl" method="POST" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm" @submit="submitContinue">
                <input type="hidden" name="_token" :value="csrfToken">
                <h4 class="text-sm font-semibold text-gray-900">Continue Wizard</h4>
                <p class="mt-1 text-xs text-gray-500">Save and continue to settings after the latest auto-save.</p>
                <button type="submit" class="mt-3 w-full rounded-md bg-indigo-600 px-3 py-2 text-sm text-white hover:bg-indigo-700">
                    Save and continue
                </button>
            </form>
        </aside>
    </div>
</template>

<script>
export default {
    name: 'CurriculumBuilder',
    props: {
        sections: { type: Array, required: true },
        endpoints: { type: Object, required: true },
        previewUrl: { type: String, required: true },
        continueUrl: { type: String, required: true },
    },
    data() {
        return {
            localSections: this.sections.map((section) => this.normalizeSection(section)),
            lessonTypes: ['video', 'text', 'pdf', 'presentation', 'audio', 'external', 'quiz', 'assignment'],
            statusText: 'Idle',
            statusClass: 'text-gray-600',
            importMode: 'append',
            autosaveTimer: null,
            dragState: {
                kind: null,
                sectionIndex: null,
                lessonIndex: null,
            },
        };
    },
    computed: {
        sectionList() {
            return this.localSections;
        },
        csrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        },
    },
    methods: {
        normalizeSection(section) {
            return {
                id: Number(section.id),
                title: section.title || 'Untitled Section',
                description: section.description || '',
                sort_order: Number(section.sort_order || 0),
                collapsed: Boolean(section.collapsed),
                newLessonType: 'video',
                lessons: (section.lessons || []).map((lesson) => ({
                    id: Number(lesson.id),
                    slug: lesson.slug,
                    title: lesson.title || 'Untitled Lesson',
                    type: lesson.type || 'video',
                    duration_minutes: Number(lesson.duration_minutes || 0),
                    is_free_preview: Boolean(lesson.is_free_preview),
                })),
            };
        },
        async request(url, method = 'GET', payload = null) {
            const headers = {
                'X-CSRF-TOKEN': this.csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            };

            const options = { method, headers };
            if (payload !== null) {
                headers['Content-Type'] = 'application/json';
                options.body = JSON.stringify(payload);
            }

            const response = await fetch(url, options);
            if (!response.ok) {
                let message = 'Request failed.';
                try {
                    const json = await response.json();
                    if (json.message) {
                        message = json.message;
                    }
                } catch (_error) {
                    // ignore parsing errors
                }
                throw new Error(message);
            }

            return response.json();
        },
        markStatus(text, type = 'idle') {
            this.statusText = text;
            this.statusClass = type === 'error' ? 'text-red-600' : type === 'saving' ? 'text-amber-600' : 'text-emerald-600';
        },
        toggleSection(section) {
            section.collapsed = !section.collapsed;
        },
        async addSection() {
            const title = window.prompt('Section title:');
            if (!title) {
                return;
            }

            try {
                const payload = await this.request(this.endpoints.addSection, 'POST', { title });
                this.localSections.push(this.normalizeSection(payload.section));
                await this.persistSectionOrder();
                this.scheduleAutosave();
                this.markStatus('Section added', 'ok');
            } catch (error) {
                this.markStatus(error.message, 'error');
            }
        },
        async addLesson(section) {
            const title = window.prompt('Lesson title:');
            if (!title) {
                return;
            }

            try {
                const url = this.endpoints.addLesson.replace('__SECTION__', String(section.id));
                const payload = await this.request(url, 'POST', {
                    title,
                    type: section.newLessonType || 'video',
                });
                section.lessons.push(payload.lesson);
                await this.persistLessonOrder();
                this.scheduleAutosave();
                this.markStatus('Lesson added', 'ok');
            } catch (error) {
                this.markStatus(error.message, 'error');
            }
        },
        async deleteSection(section) {
            if (!window.confirm(`Delete section "${section.title}"?`)) {
                return;
            }

            let cascade = 'delete_lessons';
            let targetSectionId = null;

            if (section.lessons.length > 0) {
                const availableTargets = this.localSections
                    .filter((item) => item.id !== section.id)
                    .map((item) => `${item.id}:${item.title}`)
                    .join(', ');

                const action = window.prompt(
                    `Section has lessons. Type "delete" to remove lessons, or "move:<sectionId>" to move them. Targets: ${availableTargets || 'none'}`,
                    'delete'
                );

                if (!action) {
                    return;
                }

                if (action.toLowerCase() === 'delete') {
                    cascade = 'delete_lessons';
                } else if (action.toLowerCase().startsWith('move:')) {
                    const parsed = Number(action.split(':')[1]);
                    if (!Number.isInteger(parsed) || parsed <= 0 || parsed === section.id) {
                        this.markStatus('Invalid move target section id.', 'error');
                        return;
                    }
                    cascade = 'move_lessons';
                    targetSectionId = parsed;
                } else {
                    this.markStatus('Invalid action. Use delete or move:<sectionId>.', 'error');
                    return;
                }
            }

            try {
                const url = this.endpoints.deleteSection.replace('__SECTION__', String(section.id));
                await this.request(url, 'DELETE', {
                    cascade,
                    target_section_id: targetSectionId,
                });

                this.localSections = this.localSections.filter((item) => item.id !== section.id);
                await this.persistSectionOrder();
                await this.persistLessonOrder();
                this.scheduleAutosave();
                this.markStatus('Section deleted', 'ok');
            } catch (error) {
                this.markStatus(error.message, 'error');
            }
        },
        onSectionDragStart(sectionIndex, event) {
            this.dragState = {
                kind: 'section',
                sectionIndex,
                lessonIndex: null,
            };
            event.dataTransfer.effectAllowed = 'move';
        },
        async onSectionDrop(targetIndex, _event) {
            if (this.dragState.kind !== 'section' || this.dragState.sectionIndex === null) {
                return;
            }

            const fromIndex = this.dragState.sectionIndex;
            if (fromIndex === targetIndex) {
                this.dragState.kind = null;
                return;
            }

            const moved = this.localSections.splice(fromIndex, 1)[0];
            this.localSections.splice(targetIndex, 0, moved);
            this.dragState.kind = null;

            try {
                await this.persistSectionOrder();
                this.scheduleAutosave();
                this.markStatus('Sections reordered', 'ok');
            } catch (error) {
                this.markStatus(error.message, 'error');
            }
        },
        onLessonDragStart(sectionIndex, lessonIndex, event) {
            this.dragState = {
                kind: 'lesson',
                sectionIndex,
                lessonIndex,
            };
            event.dataTransfer.effectAllowed = 'move';
        },
        async onLessonDrop(targetSectionIndex, targetLessonIndex, _event) {
            if (this.dragState.kind !== 'lesson') {
                return;
            }

            const fromSectionIndex = this.dragState.sectionIndex;
            const fromLessonIndex = this.dragState.lessonIndex;
            if (fromSectionIndex === null || fromLessonIndex === null) {
                return;
            }

            const fromSection = this.localSections[fromSectionIndex];
            const toSection = this.localSections[targetSectionIndex];
            if (!fromSection || !toSection) {
                return;
            }

            const [movedLesson] = fromSection.lessons.splice(fromLessonIndex, 1);
            if (!movedLesson) {
                return;
            }

            const insertIndex = targetLessonIndex === null ? toSection.lessons.length : targetLessonIndex;
            toSection.lessons.splice(insertIndex, 0, movedLesson);
            this.dragState.kind = null;

            try {
                await this.persistLessonOrder();
                this.scheduleAutosave();
                this.markStatus('Lessons reordered', 'ok');
            } catch (error) {
                this.markStatus(error.message, 'error');
            }
        },
        async persistSectionOrder() {
            await this.request(this.endpoints.reorderSections, 'POST', {
                section_ids: this.localSections.map((section) => section.id),
            });
        },
        async persistLessonOrder() {
            await this.request(this.endpoints.reorderLessons, 'POST', {
                sections: this.localSections.map((section) => ({
                    id: section.id,
                    lesson_ids: section.lessons.map((lesson) => lesson.id),
                })),
            });
        },
        scheduleAutosave() {
            this.markStatus('Saving...', 'saving');
            clearTimeout(this.autosaveTimer);
            this.autosaveTimer = setTimeout(() => {
                this.runAutosave();
            }, 900);
        },
        async runAutosave() {
            try {
                const payload = {
                    sections: this.localSections.map((section) => ({
                        id: section.id,
                        title: section.title,
                        description: section.description,
                        lessons: section.lessons.map((lesson) => ({
                            id: lesson.id,
                            title: lesson.title,
                            type: lesson.type,
                            duration_minutes: lesson.duration_minutes || 0,
                            is_free_preview: !!lesson.is_free_preview,
                        })),
                    })),
                };

                const response = await this.request(this.endpoints.autosave, 'POST', payload);
                this.markStatus(`Saved at ${response.saved_at}`, 'ok');
            } catch (error) {
                this.markStatus(error.message, 'error');
            }
        },
        async exportCurriculum() {
            try {
                const payload = await this.request(this.endpoints.export, 'GET');
                const blob = new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json' });
                const href = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = href;
                link.download = 'curriculum-export.json';
                link.click();
                URL.revokeObjectURL(href);
                this.markStatus('Curriculum exported', 'ok');
            } catch (error) {
                this.markStatus(error.message, 'error');
            }
        },
        triggerImport() {
            this.$refs.importInput?.click();
        },
        async importCurriculum(event) {
            const file = event.target.files?.[0];
            if (!file) {
                return;
            }

            try {
                const raw = await file.text();
                const parsed = JSON.parse(raw);
                const sections = Array.isArray(parsed.sections)
                    ? parsed.sections
                    : (Array.isArray(parsed.structure?.sections) ? parsed.structure.sections : []);

                if (!sections.length) {
                    throw new Error('No sections found in imported JSON file.');
                }

                const response = await this.request(this.endpoints.import, 'POST', {
                    mode: this.importMode,
                    structure: { sections },
                });

                this.localSections = (response.sections || []).map((section) => this.normalizeSection(section));
                this.markStatus('Curriculum imported', 'ok');
            } catch (error) {
                this.markStatus(error.message, 'error');
            } finally {
                event.target.value = '';
            }
        },
        async submitContinue(event) {
            event.preventDefault();
            await this.runAutosave();
            event.target.submit();
        },
    },
};
</script>
