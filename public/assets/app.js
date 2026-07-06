const state = {
    token: localStorage.getItem('ebd.token'),
    user: null,
    courses: [],
    classes: [],
    people: [],
    selectedCourse: null,
    lessonWorkArea: null,
    lessonMarkers: new Set(),
    reports: [],
    storedOpinions: [],
    classPeople: new Map(),
    pedagogicoStudents: [],
    selectedStudent: null,
    selectedStudentClasses: [],
    mediaRecorder: null,
    audioChunks: [],
};

const loginView = document.querySelector('#loginView');
const appView = document.querySelector('#appView');
const loginForm = document.querySelector('#loginForm');
const loginError = document.querySelector('#loginError');
const userLabel = document.querySelector('#userLabel');
const courseRows = document.querySelector('#courseRows');
const emptyCourses = document.querySelector('#emptyCourses');
const courseModal = document.querySelector('#courseModal');
const courseForm = document.querySelector('#courseForm');
const courseModalTitle = document.querySelector('#courseModalTitle');
const classRows = document.querySelector('#classRows');
const emptyClasses = document.querySelector('#emptyClasses');
const classModal = document.querySelector('#classModal');
const classForm = document.querySelector('#classForm');
const classModalTitle = document.querySelector('#classModalTitle');
const classesCourseTitle = document.querySelector('#classesCourseTitle');
const classPeopleModal = document.querySelector('#classPeopleModal');
const classPeopleForm = document.querySelector('#classPeopleForm');
const classPeopleModalTitle = document.querySelector('#classPeopleModalTitle');
const studentChoices = document.querySelector('#studentChoices');
const teacherChoices = document.querySelector('#teacherChoices');
const personRows = document.querySelector('#personRows');
const emptyPeople = document.querySelector('#emptyPeople');
const personModal = document.querySelector('#personModal');
const personForm = document.querySelector('#personForm');
const personModalTitle = document.querySelector('#personModalTitle');
const peopleImportFile = document.querySelector('#peopleImportFile');
const calendarMonth = document.querySelector('#calendarMonth');
const calendarGrid = document.querySelector('#calendarGrid');
const lessonModal = document.querySelector('#lessonModal');
const selectedLessonLabel = document.querySelector('#selectedLessonLabel');
const lessonDate = document.querySelector('#lessonDate');
const lessonClass = document.querySelector('#lessonClass');
const lessonForm = document.querySelector('#lessonForm');
const lessonMessage = document.querySelector('#lessonMessage');
const attendanceRows = document.querySelector('#attendanceRows');
const emptyAttendance = document.querySelector('#emptyAttendance');
const pedagogicoStudentRows = document.querySelector('#pedagogicoStudentRows');
const emptyPedagogicoStudents = document.querySelector('#emptyPedagogicoStudents');
const studentReportsTitle = document.querySelector('#studentReportsTitle');
const reportRows = document.querySelector('#reportRows');
const emptyReports = document.querySelector('#emptyReports');
const storedOpinionRows = document.querySelector('#storedOpinionRows');
const emptyStoredOpinions = document.querySelector('#emptyStoredOpinions');
const reportModal = document.querySelector('#reportModal');
const reportForm = document.querySelector('#reportForm');
const reportModalTitle = document.querySelector('#reportModalTitle');
const startRecordingButton = document.querySelector('#startRecordingButton');
const stopRecordingButton = document.querySelector('#stopRecordingButton');
const recordingStatus = document.querySelector('#recordingStatus');
const opinionModal = document.querySelector('#opinionModal');
const opinionModalTitle = document.querySelector('#opinionModalTitle');
const opinionText = document.querySelector('#opinionText');
const settingsForm = document.querySelector('#settingsForm');
const openaiStatus = document.querySelector('#openaiStatus');
const logoPreview = document.querySelector('#logoPreview');
const logoPlaceholder = document.querySelector('#logoPlaceholder');
const confirmModal = document.querySelector('#confirmModal');
const confirmTitle = document.querySelector('#confirmTitle');
const confirmMessage = document.querySelector('#confirmMessage');
const confirmActionButton = document.querySelector('#confirmActionButton');
const cancelConfirmButton = document.querySelector('#cancelConfirmButton');

document.querySelectorAll('.nav-item[data-page]').forEach((button) => {
    button.addEventListener('click', () => showPage(button.dataset.page));
});

loginForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    loginError.textContent = '';

    const form = new FormData(loginForm);
    const response = await api('/api/login', {
        method: 'POST',
        body: {
            email: form.get('email'),
            password: form.get('password'),
        },
        public: true,
    });

    if (response.error) {
        loginError.textContent = response.error;
        return;
    }

    state.token = response.token;
    state.user = response.user;
    localStorage.setItem('ebd.token', state.token);
    await enterApp();
});

document.querySelector('#logoutButton').addEventListener('click', () => {
    localStorage.removeItem('ebd.token');
    state.token = null;
    state.user = null;
    showLogin();
});

document.querySelector('#newCourseButton').addEventListener('click', () => openCourseModal());
document.querySelector('#closeCourseModal').addEventListener('click', () => courseModal.close());
document.querySelector('#cancelCourseButton').addEventListener('click', () => courseModal.close());
document.querySelector('#newClassButton').addEventListener('click', () => openClassModal());
document.querySelector('#backToCoursesButton').addEventListener('click', () => showPage('courses'));
document.querySelector('#closeClassModal').addEventListener('click', () => classModal.close());
document.querySelector('#cancelClassButton').addEventListener('click', () => classModal.close());
document.querySelector('#closeClassPeopleModal').addEventListener('click', () => classPeopleModal.close());
document.querySelector('#cancelClassPeopleButton').addEventListener('click', () => classPeopleModal.close());
document.querySelector('#newPersonButton').addEventListener('click', () => openPersonModal());
document.querySelector('#importPeopleButton').addEventListener('click', () => peopleImportFile.click());
peopleImportFile.addEventListener('change', () => importPeopleFromSpreadsheet());
document.querySelector('#closePersonModal').addEventListener('click', () => personModal.close());
document.querySelector('#cancelPersonButton').addEventListener('click', () => personModal.close());
document.querySelector('#previousMonthButton').addEventListener('click', () => changeCalendarMonth(-1));
document.querySelector('#nextMonthButton').addEventListener('click', () => changeCalendarMonth(1));
document.querySelector('#closeLessonModal').addEventListener('click', () => lessonModal.close());
document.querySelector('#cancelLessonButton').addEventListener('click', () => lessonModal.close());
calendarMonth.addEventListener('change', () => loadLessonMarkers());
document.querySelector('#newReportButton').addEventListener('click', () => openReportModal());
document.querySelector('#closeReportModal').addEventListener('click', () => reportModal.close());
document.querySelector('#cancelReportButton').addEventListener('click', () => reportModal.close());
document.querySelector('#backToPedagogicoButton').addEventListener('click', () => showPage('pedagogico'));
document.querySelector('#studentOpinionButton').addEventListener('click', () => openStudentOpinion());
document.querySelector('#closeOpinionModal').addEventListener('click', () => opinionModal.close());
document.querySelector('#cancelOpinionButton').addEventListener('click', () => opinionModal.close());
document.querySelector('#copyOpinionButton').addEventListener('click', () => copyOpinion());
document.querySelector('#printOpinionButton').addEventListener('click', () => printOpinion());
document.querySelector('#closeConfirmModal').addEventListener('click', () => confirmModal.close('cancel'));
cancelConfirmButton.addEventListener('click', () => confirmModal.close('cancel'));
startRecordingButton.addEventListener('click', () => startRecording());
stopRecordingButton.addEventListener('click', () => stopRecording());
settingsForm.elements.logo_file.addEventListener('change', async () => {
    const file = settingsForm.elements.logo_file.files[0] || null;

    if (file === null) {
        return;
    }

    const logoData = await readLogoFile(file);

    if (logoData?.error) {
        alert(logoData.error);
        settingsForm.elements.logo_file.value = '';
        return;
    }

    renderLogoPreview(logoData);
});
settingsForm.addEventListener('submit', async (event) => {
    event.preventDefault();

    const form = new FormData(settingsForm);
    const logoFile = form.get('logo_file');
    const logoData = logoFile instanceof File && logoFile.size > 0
        ? await readLogoFile(logoFile)
        : null;

    if (logoData?.error) {
        alert(logoData.error);
        return;
    }

    const response = await api('/api/settings/openai', {
        method: 'PUT',
        body: {
            api_key: form.get('api_key'),
            model: form.get('model'),
            opinion_prompt: form.get('opinion_prompt'),
            app_logo_data: logoData,
            remove_logo: form.get('remove_logo') === 'on',
        },
    });

    if (response.error) {
        alert(response.error);
        return;
    }

    settingsForm.elements.api_key.value = '';
    settingsForm.elements.logo_file.value = '';
    settingsForm.elements.remove_logo.checked = false;
    renderSettings(response.data);
    alert('Configuracoes salvas.');
});

courseForm.addEventListener('submit', async (event) => {
    event.preventDefault();

    const form = new FormData(courseForm);
    const id = form.get('id');
    const payload = {
        name: form.get('name'),
        description: form.get('description'),
        active: form.get('active') === 'on',
    };

    const response = await api(id ? `/api/courses/${id}` : '/api/courses', {
        method: id ? 'PUT' : 'POST',
        body: payload,
    });

    if (response.error) {
        alert(response.error);
        return;
    }

    courseModal.close();
    await loadCourses();
});

classForm.addEventListener('submit', async (event) => {
    event.preventDefault();

    const form = new FormData(classForm);
    const id = form.get('id');
    const payload = {
        course_id: Number(form.get('course_id')),
        name: form.get('name'),
        description: form.get('description'),
        active: form.get('active') === 'on',
    };

    const response = await api(id ? `/api/classes/${id}` : '/api/classes', {
        method: id ? 'PUT' : 'POST',
        body: payload,
    });

    if (response.error) {
        alert(response.error);
        return;
    }

    classModal.close();
    await loadClasses();
});

classPeopleForm.addEventListener('submit', async (event) => {
    event.preventDefault();

    const classId = classPeopleForm.elements.class_id.value;
    const response = await api(`/api/classes/${classId}/people`, {
        method: 'PUT',
        body: {
            students: checkedValues(studentChoices),
            teachers: checkedValues(teacherChoices),
        },
    });

    if (response.error) {
        alert(response.error);
        return;
    }

    state.classPeople.delete(Number(classId));
    classPeopleModal.close();
});

personForm.addEventListener('submit', async (event) => {
    event.preventDefault();

    const form = new FormData(personForm);
    const id = form.get('id');
    const payload = {
        name: form.get('name'),
        email: form.get('email'),
        phone: form.get('phone'),
        birth_date: form.get('birth_date'),
        notes: form.get('notes'),
    };

    const response = await api(id ? `/api/people/${id}` : '/api/people', {
        method: id ? 'PUT' : 'POST',
        body: payload,
    });

    if (response.error) {
        alert(response.error);
        return;
    }

    personModal.close();
    await loadPeople();
});

lessonForm.addEventListener('submit', async (event) => {
    event.preventDefault();

    if (!state.lessonWorkArea) {
        return;
    }

    const form = new FormData(lessonForm);
    const response = await api('/api/secretaria/lesson', {
        method: 'PUT',
        body: {
            class_id: Number(lessonClass.value),
            lesson_date: lessonDate.value,
            title: form.get('title'),
            teacher_person_id: Number(form.get('teacher_person_id')),
            notes: form.get('notes'),
            attendance: readAttendanceRows(),
        },
    });

    if (response.error) {
        alert(response.error);
        return;
    }

    state.lessonWorkArea = response.data;
    renderLessonWorkArea(response.data);
    await loadLessonMarkers();
    lessonModal.close();
    alert('Chamada salva.');
});

reportForm.addEventListener('submit', async (event) => {
    event.preventDefault();

    const form = new FormData(reportForm);
    const id = form.get('id');
    const response = await api(id ? `/api/pedagogico/reports/${id}` : '/api/pedagogico/reports', {
        method: id ? 'PUT' : 'POST',
        body: {
            class_id: Number(form.get('class_id')),
            student_person_id: Number(form.get('student_person_id')),
            report_date: form.get('report_date'),
            title: form.get('title'),
            body: form.get('body'),
        },
    });

    if (response.error) {
        alert(response.error);
        return;
    }

    reportModal.close();
    await loadReports();
});

async function bootstrap() {
    await loadBranding();

    if (!state.token) {
        showLogin();
        return;
    }

    const response = await api('/api/me');

    if (response.error) {
        localStorage.removeItem('ebd.token');
        showLogin();
        return;
    }

    state.user = response.user;
    await enterApp();
}

async function enterApp() {
    loginView.classList.add('hidden');
    appView.classList.remove('hidden');
    userLabel.textContent = `${state.user.name} - ${state.user.role}`;
    await loadCourses();
    await loadClasses();
    await loadPeople();
    calendarMonth.value = currentMonthValue();
    await loadLessonMarkers();
    await loadPedagogicoStudents();
    await loadSettings();
}

function showLogin() {
    appView.classList.add('hidden');
    loginView.classList.remove('hidden');
}

async function loadBranding() {
    const response = await api('/api/branding', { public: true });

    if (!response.error) {
        applyBranding(response.data.app_logo_data || '');
    }
}

async function loadCourses() {
    const response = await api('/api/courses');

    if (response.error) {
        alert(response.error);
        return;
    }

    state.courses = response.data;
    renderCourses();
}

function renderCourses() {
    courseRows.innerHTML = '';
    emptyCourses.classList.toggle('hidden', state.courses.length > 0);

    state.courses.forEach((course) => {
        const row = document.createElement('div');
        row.className = 'row course-row';
        row.innerHTML = `
            <span>${escapeHtml(course.name)}</span>
            <span>${escapeHtml(course.description || '-')}</span>
            <span>${Number(course.active) === 1 ? 'Ativo' : 'Inativo'}</span>
            <span class="actions">
                <button class="ghost-icon" title="Classes" aria-label="Classes">☰</button>
                <button class="ghost-icon" title="Editar" aria-label="Editar">✎</button>
                <button class="ghost-icon danger" title="Excluir" aria-label="Excluir">×</button>
            </span>
        `;

        const [classesButton, editButton, deleteButton] = row.querySelectorAll('button');
        classesButton.addEventListener('click', () => showClassesForCourse(course));
        editButton.addEventListener('click', () => openCourseModal(course));
        deleteButton.addEventListener('click', () => deleteCourse(course));
        courseRows.appendChild(row);
    });
}

async function loadClasses() {
    const response = await api('/api/classes');

    if (response.error) {
        alert(response.error);
        return;
    }

    state.classes = response.data;
    renderClasses();
    renderSecretariaCalendar();
}

function renderClasses() {
    classRows.innerHTML = '';
    const classes = state.selectedCourse
        ? state.classes.filter((item) => Number(item.course_id) === Number(state.selectedCourse.id))
        : state.classes;

    emptyClasses.classList.toggle('hidden', classes.length > 0);

    classes.forEach((item) => {
        const row = document.createElement('div');
        row.className = 'row class-row';
        row.innerHTML = `
            <span>${escapeHtml(item.name)}</span>
            <span>${escapeHtml(item.course_name)}</span>
            <span>${Number(item.active) === 1 ? 'Ativa' : 'Inativa'}</span>
            <span class="actions">
                <button class="ghost-icon" title="Alunos e professores" aria-label="Alunos e professores">👥</button>
                <button class="ghost-icon" title="Editar" aria-label="Editar">✎</button>
                <button class="ghost-icon danger" title="Excluir" aria-label="Excluir">×</button>
            </span>
        `;

        const [peopleButton, editButton, deleteButton] = row.querySelectorAll('button');
        peopleButton.addEventListener('click', () => openClassPeopleModal(item));
        editButton.addEventListener('click', () => openClassModal(item));
        deleteButton.addEventListener('click', () => deleteClass(item));
        classRows.appendChild(row);
    });
}

async function loadPeople() {
    const response = await api('/api/people');

    if (response.error) {
        alert(response.error);
        return;
    }

    state.people = response.data;
    renderPeople();
}

function renderPeople() {
    personRows.innerHTML = '';
    emptyPeople.classList.toggle('hidden', state.people.length > 0);

    state.people.forEach((person) => {
        const row = document.createElement('div');
        row.className = 'row person-row';
        row.innerHTML = `
            <span>${escapeHtml(person.name)}</span>
            <span>${escapeHtml(person.email || '-')}</span>
            <span>${escapeHtml(person.phone || '-')}</span>
            <span class="actions">
                <button class="ghost-icon" title="Editar" aria-label="Editar">✎</button>
                <button class="ghost-icon danger" title="Excluir" aria-label="Excluir">×</button>
            </span>
        `;

        const [editButton, deleteButton] = row.querySelectorAll('button');
        editButton.addEventListener('click', () => openPersonModal(person));
        deleteButton.addEventListener('click', () => deletePerson(person));
        personRows.appendChild(row);
    });
}

async function importPeopleFromSpreadsheet() {
    const file = peopleImportFile.files[0] || null;
    peopleImportFile.value = '';

    if (file === null) {
        return;
    }

    if (!file.name.toLowerCase().endsWith('.csv')) {
        alert('Envie uma planilha em CSV.');
        return;
    }

    let response = await uploadPeopleSpreadsheet(peopleImportFormData(file));

    if (response.error) {
        alert(response.error);
        return;
    }

    if (response.data?.needs_review) {
        const decisions = await reviewPeopleGenderConflicts(response.data.conflicts);

        if (decisions === null) {
            return;
        }

        response = await uploadPeopleSpreadsheet(peopleImportFormData(file, decisions));

        if (response.error) {
            alert(response.error);
            return;
        }

        if (response.data?.needs_review) {
            alert('Ainda ha divergencias de sexo para revisar. Tente importar novamente e revise todos os nomes apontados.');
            return;
        }
    }

    const result = response.data;

    if (!result || !Number.isInteger(result.created) || !Number.isInteger(result.updated) || !Number.isInteger(result.skipped)) {
        alert('A importacao nao retornou um resultado valido. Nenhum registro foi confirmado na tela.');
        return;
    }

    await loadPeople();
    alert(`Importacao concluida. Novas: ${result.created}. Atualizadas: ${result.updated}. Ignoradas: ${result.skipped}.`);
}

function peopleImportFormData(file, genderDecisions = {}) {
    const formData = new FormData();
    formData.append('spreadsheet', file);
    formData.append('gender_decisions', JSON.stringify(genderDecisions));

    return formData;
}

async function reviewPeopleGenderConflicts(conflicts) {
    const decisions = {};

    for (const conflict of conflicts) {
        const keepSpreadsheetGender = await askConfirmation(
            `A planilha informa ${conflict.provided_gender} para ${conflict.name}, mas o nome sugere ${conflict.suggested_gender}. Deseja manter o sexo informado na planilha?`,
            'Revisar sexo',
            'Manter'
        );

        if (keepSpreadsheetGender) {
            decisions[conflict.row_number] = conflict.provided_gender;
            continue;
        }

        const corrected = prompt(
            `Informe o sexo correto para ${conflict.name}: MASCULINO ou FEMININO`,
            conflict.suggested_gender
        );

        if (corrected === null) {
            return null;
        }

        const normalized = normalizeGenderInput(corrected);

        if (normalized === '') {
            alert('Importacao cancelada: informe MASCULINO ou FEMININO.');
            return null;
        }

        decisions[conflict.row_number] = normalized;
    }

    return decisions;
}

function normalizeGenderInput(value) {
    const normalized = String(value || '').trim().toUpperCase();

    if (['M', 'MASC', 'MASCULINO'].includes(normalized)) {
        return 'MASCULINO';
    }

    if (['F', 'FEM', 'FEMININO'].includes(normalized)) {
        return 'FEMININO';
    }

    return '';
}

async function uploadPeopleSpreadsheet(formData) {
    const response = await fetch('/api/people/import', {
        method: 'POST',
        headers: {
            Authorization: `Bearer ${state.token}`,
        },
        body: formData,
    });
    const text = await response.text();

    try {
        const payload = JSON.parse(text);

        if (!response.ok && !payload.error) {
            return { error: `Nao foi possivel importar a planilha. HTTP ${response.status}` };
        }

        return payload;
    } catch {
        return {
            error: `Resposta inesperada ao importar planilha: ${text.slice(0, 220) || `HTTP ${response.status}`}`,
        };
    }
}

function openCourseModal(course = null) {
    courseForm.reset();
    courseModalTitle.textContent = course ? 'Editar curso' : 'Novo curso';
    courseForm.elements.id.value = course?.id || '';
    courseForm.elements.name.value = course?.name || '';
    courseForm.elements.description.value = course?.description || '';
    courseForm.elements.active.checked = course ? Number(course.active) === 1 : true;
    courseModal.showModal();
}

function openClassModal(item = null) {
    if (!state.selectedCourse && !item) {
        alert('Abra as classes a partir de um curso.');
        return;
    }

    classForm.reset();
    classModalTitle.textContent = item ? 'Editar classe' : 'Nova classe';
    classForm.elements.id.value = item?.id || '';
    classForm.elements.course_id.value = item?.course_id || state.selectedCourse.id;
    classForm.elements.name.value = item?.name || '';
    classForm.elements.description.value = item?.description || '';
    classForm.elements.active.checked = item ? Number(item.active) === 1 : true;
    classModal.showModal();
}

function openPersonModal(person = null) {
    personForm.reset();
    personModalTitle.textContent = person ? 'Editar pessoa' : 'Nova pessoa';
    personForm.elements.id.value = person?.id || '';
    personForm.elements.name.value = person?.name || '';
    personForm.elements.email.value = person?.email || '';
    personForm.elements.phone.value = person?.phone || '';
    personForm.elements.birth_date.value = person?.birth_date || '';
    personForm.elements.notes.value = person?.notes || '';
    personModal.showModal();
}

async function openClassPeopleModal(item) {
    if (state.people.length === 0) {
        alert('Cadastre pessoas antes de associar alunos e professores.');
        return;
    }

    const response = await api(`/api/classes/${item.id}/people`);

    if (response.error) {
        alert(response.error);
        return;
    }

    const studentIds = response.data.students.map((person) => Number(person.id));
    const teacherIds = response.data.teachers.map((person) => Number(person.id));

    classPeopleForm.reset();
    classPeopleForm.elements.class_id.value = item.id;
    classPeopleModalTitle.textContent = item.name;
    renderPersonChoices(studentChoices, 'students', studentIds);
    renderPersonChoices(teacherChoices, 'teachers', teacherIds);
    classPeopleModal.showModal();
}

function renderPersonChoices(container, name, selectedIds) {
    container.innerHTML = state.people
        .map((person) => `
            <label>
                <input type="checkbox" name="${name}" value="${person.id}" ${selectedIds.includes(Number(person.id)) ? 'checked' : ''}>
                <span>${escapeHtml(person.name)}</span>
            </label>
        `)
        .join('');
}

function checkedValues(container) {
    return Array.from(container.querySelectorAll('input:checked')).map((input) => Number(input.value));
}

async function loadLessonWorkArea() {
    if (!lessonDate.value || !lessonClass.value) {
        alert('Selecione uma classe no calendario.');
        return;
    }

    const params = new URLSearchParams({
        class_id: lessonClass.value,
        lesson_date: lessonDate.value,
    });
    const response = await api(`/api/secretaria/lesson?${params.toString()}`);

    if (response.error) {
        alert(response.error);
        return;
    }

    state.lessonWorkArea = response.data;
    renderLessonWorkArea(response.data);
}

function renderLessonWorkArea(data) {
    lessonMessage.classList.add('hidden');
    selectedLessonLabel.textContent = `${formatDateLabel(lessonDate.value)} - ${data.class.course_name} / ${data.class.name}`;
    lessonForm.elements.title.value = data.lesson?.title || '';
    lessonForm.elements.notes.value = data.lesson?.notes || '';
    lessonForm.elements.teacher_person_id.innerHTML = data.teachers
        .map((teacher) => `<option value="${teacher.id}">${escapeHtml(teacher.name)}</option>`)
        .join('');

    if (data.lesson?.teacher_person_id) {
        lessonForm.elements.teacher_person_id.value = data.lesson.teacher_person_id;
    }

    attendanceRows.innerHTML = '';
    emptyAttendance.classList.toggle('hidden', data.students.length > 0);

    data.students.forEach((student) => {
        const existing = data.attendance[student.id] || {};
        const isPresent = existing.status ? existing.status === 'presente' : true;
        const row = document.createElement('div');
        row.className = 'row attendance-row';
        row.dataset.studentId = student.id;
        row.innerHTML = `
            <span>${escapeHtml(student.name)}</span>
            <span><input name="present" type="checkbox" ${isPresent ? 'checked' : ''}></span>
            <span><input name="notes" value="${escapeHtml(existing.notes || '')}" maxlength="180"></span>
        `;
        attendanceRows.appendChild(row);
    });

    lessonModal.showModal();
}

function readAttendanceRows() {
    return Array.from(attendanceRows.querySelectorAll('.attendance-row')).map((row) => ({
        student_person_id: Number(row.dataset.studentId),
        status: row.querySelector('input[name="present"]').checked ? 'presente' : 'ausente',
        notes: row.querySelector('input[name="notes"]').value,
    }));
}

function renderSecretariaCalendar() {
    if (!calendarGrid || !calendarMonth.value) {
        return;
    }

    calendarGrid.innerHTML = '';
    const [year, month] = calendarMonth.value.split('-').map(Number);
    const firstDay = new Date(year, month - 1, 1);
    const daysInMonth = new Date(year, month, 0).getDate();
    const activeClasses = state.classes.filter((item) => Number(item.active) === 1);

    for (let i = 0; i < firstDay.getDay(); i += 1) {
        calendarGrid.appendChild(emptyCalendarCell());
    }

    for (let day = 1; day <= daysInMonth; day += 1) {
        const date = new Date(year, month - 1, day);
        const dateValue = formatDateValue(date);
        const cell = document.createElement('section');
        const isSunday = date.getDay() === 0;
        cell.className = `calendar-cell${isSunday ? ' sunday' : ''}`;
        cell.innerHTML = `<strong>${day}</strong>`;

        if (isSunday) {
            const list = document.createElement('div');
            list.className = 'calendar-class-list';

            activeClasses.forEach((item) => {
                const initials = shortLabelFor(item.name);
                const hasLesson = state.lessonMarkers.has(markerKey(dateValue, item.id));
                const button = document.createElement('button');
                button.type = 'button';
                button.className = `calendar-class-button ${hasLesson ? 'saved' : 'unsaved'}`;
                button.title = `${item.course_name} - ${item.name} (${hasLesson ? 'chamada salva' : 'sem chamada salva'})`;
                button.setAttribute('aria-label', `Abrir chamada de ${item.course_name} - ${item.name}`);
                button.textContent = initials;
                button.addEventListener('click', () => openLessonFromCalendar(dateValue, item));
                list.appendChild(button);
            });

            if (activeClasses.length === 0) {
                const empty = document.createElement('p');
                empty.className = 'calendar-empty';
                empty.textContent = 'Sem classes cadastradas';
                list.appendChild(empty);
            }

            cell.appendChild(list);
        }

        calendarGrid.appendChild(cell);
    }
}

async function loadLessonMarkers() {
    if (!calendarMonth.value) {
        return;
    }

    const params = new URLSearchParams({ month: calendarMonth.value });
    const response = await api(`/api/secretaria/month?${params.toString()}`);

    if (response.error) {
        alert(response.error);
        return;
    }

    state.lessonMarkers = new Set(response.data.map((item) => markerKey(item.lesson_date, item.class_id)));
    renderSecretariaCalendar();
}

function markerKey(dateValue, classId) {
    return `${dateValue}:${Number(classId)}`;
}

function emptyCalendarCell() {
    const cell = document.createElement('section');
    cell.className = 'calendar-cell muted-cell';

    return cell;
}

function openLessonFromCalendar(dateValue, item) {
    lessonDate.value = dateValue;
    lessonClass.value = item.id;
    loadLessonWorkArea();
}

function changeCalendarMonth(delta) {
    const [year, month] = calendarMonth.value.split('-').map(Number);
    const date = new Date(year, month - 1 + delta, 1);
    calendarMonth.value = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
    loadLessonMarkers();
}

function currentMonthValue() {
    const date = new Date();

    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
}

function todayValue() {
    return formatDateValue(new Date());
}

function formatDateValue(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function formatDateLabel(value) {
    const [year, month, day] = value.split('-');

    return `${day}/${month}/${year}`;
}

function formatDateTimeLabel(value) {
    const [datePart, timePart = ''] = String(value).split(' ');

    return `${formatDateLabel(datePart)}${timePart ? ` ${timePart.slice(0, 5)}` : ''}`;
}

function shortLabelFor(value) {
    const text = String(value)
        .trim()
        .replace(/\s+/g, ' ');

    if (text === '') {
        return '?';
    }

    const words = text.split(' ');

    if (words.length === 1) {
        return words[0].slice(0, 5).toUpperCase();
    }

    return words
        .map((word) => word.slice(0, 3))
        .join('')
        .slice(0, 6)
        .toUpperCase();
}

async function loadPedagogicoStudents() {
    const response = await api('/api/pedagogico/students');

    if (response.error) {
        alert(response.error);
        return;
    }

    state.pedagogicoStudents = response.data;
    renderPedagogicoStudents();
}

function renderPedagogicoStudents() {
    pedagogicoStudentRows.innerHTML = '';
    emptyPedagogicoStudents.classList.toggle('hidden', state.pedagogicoStudents.length > 0);

    state.pedagogicoStudents.forEach((student) => {
        const row = document.createElement('div');
        row.className = 'row student-report-row';
        row.innerHTML = `
            <span>${escapeHtml(student.name)}</span>
            <span>${escapeHtml(student.email || '-')}</span>
            <span>${Number(student.class_count)}</span>
            <span class="actions">
                <button class="ghost-icon" title="Relatorios" aria-label="Relatorios">☰</button>
            </span>
        `;

        row.querySelector('button').addEventListener('click', () => openStudentReports(student));
        pedagogicoStudentRows.appendChild(row);
    });
}

async function openStudentReports(student) {
    state.selectedStudent = student;
    studentReportsTitle.textContent = student.name;
    state.selectedStudentClasses = await classesForStudent(Number(student.id));
    await loadReports();
    await loadStoredOpinions();
    showPage('studentReports');
}

async function openStudentOpinion() {
    if (!state.selectedStudent) {
        return;
    }

    opinionModalTitle.textContent = state.selectedStudent.name;
    opinionText.value = 'Gerando parecer pela IA...';
    opinionModal.showModal();

    const response = await api(`/api/pedagogico/students/${state.selectedStudent.id}/opinion`);

    if (response.error) {
        opinionText.value = response.error;
        return;
    }

    opinionText.value = response.data.text;
    await loadStoredOpinions();
}

async function loadStoredOpinions() {
    if (!state.selectedStudent) {
        return;
    }

    const response = await api(`/api/pedagogico/students/${state.selectedStudent.id}/opinions`);

    if (response.error) {
        alert(response.error);
        return;
    }

    state.storedOpinions = response.data;
    renderStoredOpinions();
}

function renderStoredOpinions() {
    storedOpinionRows.innerHTML = '';
    emptyStoredOpinions.classList.toggle('hidden', state.storedOpinions.length > 0);

    state.storedOpinions.forEach((opinion) => {
        const row = document.createElement('div');
        row.className = 'row opinion-row';
        row.innerHTML = `
            <span>${formatDateTimeLabel(opinion.created_at)}</span>
            <span>${Number(opinion.source_report_count)}</span>
            <span>${escapeHtml(opinion.model)}</span>
            <span>${escapeHtml(opinion.author_name)}</span>
            <span class="actions">
                <button class="ghost-icon" title="Abrir" aria-label="Abrir">☰</button>
            </span>
        `;

        row.querySelector('button').addEventListener('click', () => openStoredOpinion(opinion));
        storedOpinionRows.appendChild(row);
    });
}

function openStoredOpinion(opinion) {
    opinionModalTitle.textContent = `${state.selectedStudent.name} - ${formatDateTimeLabel(opinion.created_at)}`;
    opinionText.value = opinion.body;
    opinionModal.showModal();
}

async function copyOpinion() {
    await navigator.clipboard.writeText(opinionText.value);
    alert('Parecer copiado.');
}

function printOpinion() {
    const printWindow = window.open('', '_blank');

    if (!printWindow) {
        alert('Nao foi possivel abrir a janela de impressao.');
        return;
    }

    printWindow.document.write(`<pre style="font-family: Arial, sans-serif; white-space: pre-wrap;">${escapeHtml(opinionText.value)}</pre>`);
    printWindow.document.close();
    printWindow.print();
}

async function loadReports() {
    if (!state.selectedStudent) {
        return;
    }

    const params = new URLSearchParams();
    params.set('student_id', state.selectedStudent.id);

    const response = await api(`/api/pedagogico/reports${params.toString() ? `?${params.toString()}` : ''}`);

    if (response.error) {
        alert(response.error);
        return;
    }

    state.reports = response.data;
    renderReports();
}

function renderReports() {
    reportRows.innerHTML = '';
    emptyReports.classList.toggle('hidden', state.reports.length > 0);

    state.reports.forEach((report) => {
        const row = document.createElement('div');
        row.className = 'row report-row';
        row.innerHTML = `
            <span>${formatDateLabel(report.report_date)}</span>
            <span>${escapeHtml(report.course_name)} / ${escapeHtml(report.class_name)}</span>
            <span>${escapeHtml(report.title)}</span>
            <span>${escapeHtml(report.author_name)}</span>
            <span class="actions">
                <button class="ghost-icon" title="Editar" aria-label="Editar">✎</button>
                <button class="ghost-icon danger" title="Excluir" aria-label="Excluir">×</button>
            </span>
        `;

        const [editButton, deleteButton] = row.querySelectorAll('button');
        editButton.addEventListener('click', () => openReportModal(report));
        deleteButton.addEventListener('click', () => deleteReport(report));
        reportRows.appendChild(row);
    });
}

async function openReportModal(report = null) {
    if (!state.selectedStudent) {
        alert('Escolha um aluno antes de criar relatorios.');
        return;
    }

    if (state.selectedStudentClasses.length === 0) {
        alert('Este aluno nao participa de nenhuma classe.');
        return;
    }

    reportForm.reset();
    reportModalTitle.textContent = report ? 'Editar relatorio' : 'Novo relatorio';
    reportForm.elements.id.value = report?.id || '';
    reportForm.elements.student_person_id.value = state.selectedStudent.id;
    reportForm.elements.student_name.value = state.selectedStudent.name;
    reportForm.elements.class_id.innerHTML = state.selectedStudentClasses
        .map((item) => `<option value="${item.id}">${escapeHtml(item.course_name)} - ${escapeHtml(item.name)}</option>`)
        .join('');
    reportForm.elements.class_id.value = report?.class_id || state.selectedStudentClasses[0].id;
    reportForm.elements.report_date.value = report?.report_date || todayValue();
    reportForm.elements.title.value = report?.title || '';
    reportForm.elements.body.value = report?.body || '';
    recordingStatus.textContent = 'Use o microfone para preencher o relatorio por transcricao.';
    startRecordingButton.classList.remove('hidden');
    stopRecordingButton.classList.add('hidden');
    reportModal.showModal();
}

async function deleteReport(report) {
    const confirmed = await askConfirmation(`Excluir o relatorio "${report.title}"?`);

    if (!confirmed) {
        return;
    }

    const response = await api(`/api/pedagogico/reports/${report.id}`, { method: 'DELETE' });

    if (response.error) {
        alert(response.error);
        return;
    }

    await loadReports();
}

async function classesForStudent(studentId) {
    const response = await api(`/api/pedagogico/students/${studentId}/classes`);

    if (response.error) {
        alert(response.error);
        return [];
    }

    return response.data;
}

async function startRecording() {
    if (!navigator.mediaDevices?.getUserMedia || typeof MediaRecorder === 'undefined') {
        alert('Este navegador nao permite gravacao de audio nesta tela.');
        return;
    }

    try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        state.audioChunks = [];
        state.mediaRecorder = new MediaRecorder(stream, { mimeType: preferredAudioMimeType() });
        state.mediaRecorder.ondataavailable = (event) => {
            if (event.data.size > 0) {
                state.audioChunks.push(event.data);
            }
        };
        state.mediaRecorder.onstop = async () => {
            stream.getTracks().forEach((track) => track.stop());
            await transcribeRecording();
        };
        state.mediaRecorder.start();
        recordingStatus.textContent = 'Gravando...';
        startRecordingButton.classList.add('hidden');
        stopRecordingButton.classList.remove('hidden');
    } catch {
        alert('Nao foi possivel acessar o microfone.');
    }
}

function stopRecording() {
    if (state.mediaRecorder && state.mediaRecorder.state !== 'inactive') {
        recordingStatus.textContent = 'Transcrevendo audio...';
        state.mediaRecorder.stop();
    }
}

async function transcribeRecording() {
    const blob = new Blob(state.audioChunks, { type: preferredAudioMimeType() });
    const formData = new FormData();
    formData.append('audio', blob, 'relatorio.webm');

    const response = await uploadAudioForTranscription(formData);

    startRecordingButton.classList.remove('hidden');
    stopRecordingButton.classList.add('hidden');

    if (response.error) {
        recordingStatus.textContent = response.error;
        return;
    }

    reportForm.elements.body.value = [reportForm.elements.body.value, response.text]
        .filter(Boolean)
        .join(reportForm.elements.body.value ? '\n\n' : '');
    recordingStatus.textContent = 'Transcricao adicionada ao relatorio.';
}

function preferredAudioMimeType() {
    if (typeof MediaRecorder !== 'undefined' && MediaRecorder.isTypeSupported('audio/webm')) {
        return 'audio/webm';
    }

    return 'audio/mp4';
}

async function uploadAudioForTranscription(formData) {
    const response = await fetch('/api/pedagogico/transcribe', {
        method: 'POST',
        headers: {
            Authorization: `Bearer ${state.token}`,
        },
        body: formData,
    });
    const text = await response.text();

    try {
        return JSON.parse(text);
    } catch {
        return {
            error: `Resposta inesperada ao transcrever audio: ${text.slice(0, 220) || `HTTP ${response.status}`}`,
        };
    }
}

async function loadSettings() {
    const response = await api('/api/settings');

    if (response.error) {
        openaiStatus.textContent = response.error;
        return;
    }

    renderSettings(response.data);
}

function renderSettings(settings) {
    openaiStatus.textContent = settings.openai_configured
        ? `Chave da OpenAI configurada. Modelo atual: ${settings.openai_model}`
        : 'Chave da OpenAI ainda nao configurada.';
    settingsForm.elements.model.value = settings.openai_model || 'gpt-5.5';
    settingsForm.elements.opinion_prompt.value = settings.openai_opinion_prompt || '';
    applyBranding(settings.app_logo_data || '');
    renderLogoPreview(settings.app_logo_data || '');
}

function applyBranding(logoData) {
    document.querySelectorAll('.app-logo').forEach((image) => {
        image.src = logoData || '';
        image.classList.toggle('hidden', logoData === '');
    });

    document.querySelectorAll('.brand-fallback').forEach((fallback) => {
        fallback.classList.toggle('hidden', logoData !== '');
    });
}

function renderLogoPreview(logoData) {
    logoPreview.src = logoData || '';
    logoPreview.classList.toggle('hidden', logoData === '');
    logoPlaceholder.classList.toggle('hidden', logoData !== '');
}

function readLogoFile(file) {
    const allowedTypes = ['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'];

    if (!allowedTypes.includes(file.type)) {
        return { error: 'Envie uma logomarca em PNG, JPG, WebP ou SVG.' };
    }

    if (file.size > 600 * 1024) {
        return { error: 'A logomarca deve ter no maximo 600 KB.' };
    }

    return new Promise((resolve) => {
        const reader = new FileReader();
        reader.onload = () => resolve(String(reader.result || ''));
        reader.onerror = () => resolve({ error: 'Nao foi possivel ler o arquivo da logomarca.' });
        reader.readAsDataURL(file);
    });
}

function askConfirmation(message, title = 'Confirmar exclusao', confirmLabel = 'Excluir') {
    confirmTitle.textContent = title;
    confirmMessage.textContent = message;
    confirmActionButton.textContent = confirmLabel;
    confirmActionButton.disabled = false;

    return new Promise((resolve) => {
        const cleanup = () => {
            confirmActionButton.removeEventListener('click', onConfirm);
            confirmModal.removeEventListener('close', onClose);
        };
        const onConfirm = () => {
            cleanup();
            confirmModal.close('confirm');
            resolve(true);
        };
        const onClose = () => {
            cleanup();
            resolve(confirmModal.returnValue === 'confirm');
        };

        confirmActionButton.addEventListener('click', onConfirm);
        confirmModal.addEventListener('close', onClose);
        confirmModal.showModal();
    });
}

async function deleteCourse(course) {
    const confirmed = await askConfirmation(`Excluir o curso "${course.name}"?`);

    if (!confirmed) {
        return;
    }

    const response = await api(`/api/courses/${course.id}`, { method: 'DELETE' });

    if (response.error) {
        alert(response.error);
        return;
    }

    await loadCourses();
    await loadClasses();
}

async function deleteClass(item) {
    const confirmed = await askConfirmation(`Excluir a classe "${item.name}"?`);

    if (!confirmed) {
        return;
    }

    const response = await api(`/api/classes/${item.id}`, { method: 'DELETE' });

    if (response.error) {
        alert(response.error);
        return;
    }

    await loadClasses();
}

async function deletePerson(person) {
    const confirmed = await askConfirmation(`Excluir "${person.name}"?`);

    if (!confirmed) {
        return;
    }

    const response = await api(`/api/people/${person.id}`, { method: 'DELETE' });

    if (response.error) {
        alert(response.error);
        return;
    }

    await loadPeople();
}

function showClassesForCourse(course) {
    state.selectedCourse = course;
    classesCourseTitle.textContent = course.name;
    renderClasses();
    showPage('classes');
}

function showPage(page) {
    document.querySelectorAll('.app-page').forEach((section) => {
        section.classList.toggle('hidden', section.id !== `${page}Page`);
    });

    document.querySelectorAll('.nav-item[data-page]').forEach((button) => {
        button.classList.toggle('active', button.dataset.page === page);
    });
}

async function api(path, options = {}) {
    const headers = {
        'Content-Type': 'application/json',
    };

    if (state.token && !options.public) {
        headers.Authorization = `Bearer ${state.token}`;
    }

    const response = await fetch(path, {
        method: options.method || 'GET',
        headers,
        body: options.body ? JSON.stringify(options.body) : undefined,
    });

    const text = await response.text();

    try {
        return JSON.parse(text);
    } catch {
        return {
            error: `Resposta inesperada do servidor em ${path}. Verifique se iniciou o PHP com public/router.php.`,
        };
    }
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

bootstrap();
