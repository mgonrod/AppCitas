const app = Vue.createApp({
    data() {
        return {
            form: {
                name: '',
                dni: '',
                phone: '',
                email: '',
                type_appointment: 'Primera consulta'
            },
            isRevisionesAllowed: false,
            emailError: '',
            dniError: '',
            nameError: '',
        };
    },
    methods: {
        async checkDni() {
            this.dniError = ''; // Limpiar el error del DNI

            // Validar que el DNI no esté vacío
            if (!this.form.dni.trim()) {
                this.dniError = 'El DNI no puede estar vacío.';
                return;
            }

            try {
                const response = await axios.post('backend.php', {
                    action: 'validateDni',
                    dni: this.form.dni
                });

                if (response.status >= 200 && response.status < 300) {
                    const data = response.data;
                    if (data.exists) {
                        this.isRevisionesAllowed = true;
                        this.form.type_appointment = 'Revision';
                    } else {
                        this.isRevisionesAllowed = false;
                        if (this.form.type_appointment === 'Revision') {
                            this.form.type_appointment = 'Primera consulta';
                        }
                    }
                } else {
                    console.error('Error al verificar el DNI:', response.statusText);
                }
            } catch (error) {
                console.error('Error de red o del servidor:', error);
            }
        },
        validateEmail() {
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailPattern.test(this.form.email)) {
                this.emailError = 'El email introducido no es válido.';
            } else {
                this.emailError = '';
            }
        },
        async submitForm() {
            // Limpiar los errores
            this.dniError = '';
            this.emailError = '';
            this.nameError = '';

            // Validar todos los campos necesarios
            if (!this.form.name.trim()) {
                this.nameError = 'El nombre no puede estar vacío.';
                return;
            }
            if (!this.form.dni.trim()) {
                this.dniError = 'El DNI no puede estar vacío.';
                return;
            }
            if (!this.form.email.trim()) {
                this.emailError = 'El email no puede estar vacío.';
                return;
            }

            // Validar el email antes de enviar
            this.validateEmail();
            
            if (!this.emailError && !this.dniError && !this.nameError) {
                try {
                    const response = await axios.post('backend.php', {
                        action: 'createAppointment',
                        ...this.form
                    });

                    if (response.data.success) {
                        alert(`Cita agendada para el ${response.data.fecha} a las ${response.data.hora}`);
                        // Resetear el formulario si es necesario
                        this.form = {
                            name: '',
                            dni: '',
                            phone: '',
                            email: '',
                            type_appointment: 'Primera consulta'
                        };
                        this.isRevisionesAllowed = false;
                    } else {
                        alert('Error al agendar la cita: ' + (response.data.error || 'Error desconocido'));
                    }
                } catch (error) {
                    console.error('Error al enviar el formulario:', error);
                    alert('Error al enviar el formulario. Inténtalo de nuevo más tarde.');
                }
            }
        }
    }
});

app.mount('#app');