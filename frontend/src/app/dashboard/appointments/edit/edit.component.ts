import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, Validators, ReactiveFormsModule } from '@angular/forms';
import { Router, ActivatedRoute, RouterLink } from '@angular/router';
import { AppointmentService, Appointment } from '../../../services/appointment.service';
import { PatientService, Patient } from '../../../services/patient.service';
import { debounceTime, distinctUntilChanged } from 'rxjs/operators';

@Component({
  selector: 'app-edit',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterLink],
  templateUrl: './edit.component.html',
  styleUrl: './edit.component.scss',
})
export class EditComponent implements OnInit {
  appointmentForm: FormGroup;
  isLoading = false;
  loadingAppointment = true;
  errorMessage = '';
  appointmentId: number | null = null;

  // Patient autocomplete
  patientSuggestions: Patient[] = [];
  showSuggestions = false;
  searchingPatient = false;
  selectedPatientId: number | null = null;

  treatments = [
    { value: 'brackets_metalicos', label: 'Brackets Metálicos' },
    { value: 'brackets_esteticos', label: 'Brackets Estéticos' },
    { value: 'ortodoncia_invisible', label: 'Ortodoncia Invisible' },
    { value: 'ortodoncia_infantil', label: 'Ortodoncia Infantil' },
    { value: 'ortodoncia', label: 'Ortodoncia' },
    { value: 'brackets', label: 'Brackets' },
    { value: 'invisalign', label: 'Invisalign' },
    { value: 'retenedores', label: 'Retenedores' },
    { value: 'consulta', label: 'Consulta General' },
  ];

  constructor(private fb: FormBuilder, private appointmentService: AppointmentService, private patientService: PatientService, private router: Router, private route: ActivatedRoute) {
    this.appointmentForm = this.fb.group({
      patient_name: ['', [Validators.required, Validators.minLength(3)]],
      patient_email: ['', [Validators.required, Validators.email]],
      patient_phone: ['', [Validators.required, Validators.pattern(/^\d{10}$/)]],
      treatment_type: ['', Validators.required],
      appointment_date: [''],
      notes: [''],
    });
  }

  ngOnInit(): void {
    // Get appointment ID from route
    const id = this.route.snapshot.paramMap.get('id');
    if (id) {
      this.appointmentId = +id;
      this.loadAppointment();
    }

    // Listen to name, email, and phone changes for patient search
    const searchFields = ['patient_name', 'patient_email', 'patient_phone'];

    searchFields.forEach((fieldName) => {
      this.appointmentForm
        .get(fieldName)
        ?.valueChanges.pipe(debounceTime(400), distinctUntilChanged())
        .subscribe((value) => {
          if (value && value.toString().length >= 3) {
            this.searchPatients(value);
          } else {
            this.patientSuggestions = [];
            this.showSuggestions = false;
          }
        });
    });
  }

  loadAppointment(): void {
    if (!this.appointmentId) return;

    this.appointmentService.getById(this.appointmentId).subscribe({
      next: (response) => {
        const appointment = response.appointment;
        this.selectedPatientId = appointment.patient_id || null;

        // Format date for datetime-local input
        let formattedDate = '';
        if (appointment.appointment_date) {
          const date = new Date(appointment.appointment_date);
          formattedDate = this.formatDateForInput(date);
        }

        this.appointmentForm.patchValue({
          patient_name: appointment.patient_name,
          patient_email: appointment.patient_email,
          patient_phone: appointment.patient_phone,
          treatment_type: appointment.treatment_type,
          appointment_date: formattedDate,
          notes: appointment.notes || '',
        });

        this.loadingAppointment = false;
      },
      error: (error) => {
        console.error('Error loading appointment:', error);
        this.errorMessage = 'Error al cargar la cita';
        this.loadingAppointment = false;
      },
    });
  }

  formatDateForInput(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${year}-${month}-${day}T${hours}:${minutes}`;
  }

  searchPatients(query: string): void {
    this.searchingPatient = true;
    this.patientService.search(query).subscribe({
      next: (patients) => {
        this.patientSuggestions = patients;
        this.showSuggestions = patients.length > 0;
        this.searchingPatient = false;
      },
      error: (error) => {
        console.error('Error searching patients:', error);
        this.searchingPatient = false;
      },
    });
  }

  selectPatient(patient: Patient): void {
    this.selectedPatientId = patient.id;
    this.appointmentForm.patchValue({
      patient_name: patient.name,
      patient_email: patient.email,
      patient_phone: patient.phone,
    });
    this.showSuggestions = false;
    this.patientSuggestions = [];
  }

  clearSelection(): void {
    this.selectedPatientId = null;
  }

  onSubmit(): void {
    if (this.appointmentForm.invalid || !this.appointmentId) {
      this.appointmentForm.markAllAsTouched();
      return;
    }

    this.isLoading = true;
    this.errorMessage = '';

    const formData = this.appointmentForm.value;
    const appointmentData = {
      ...formData,
      patient_id: this.selectedPatientId,
      appointment_date: formData.appointment_date ? new Date(formData.appointment_date).toISOString() : null,
    };

    this.appointmentService.update(this.appointmentId, appointmentData).subscribe({
      next: (response) => {
        console.log('Cita actualizada:', response);
        this.router.navigate(['/dashboard/citas']);
      },
      error: (error) => {
        console.error('Error:', error);
        this.errorMessage = error.error?.message || 'Error al actualizar la cita';
        this.isLoading = false;
      },
      complete: () => {
        this.isLoading = false;
      },
    });
  }

  hideSuggestionsWithDelay() {
    setTimeout(() => {
      this.showSuggestions = false;
    }, 200);
  }

  get patient_name() {
    return this.appointmentForm.get('patient_name');
  }
  get patient_email() {
    return this.appointmentForm.get('patient_email');
  }
  get patient_phone() {
    return this.appointmentForm.get('patient_phone');
  }
  get treatment_type() {
    return this.appointmentForm.get('treatment_type');
  }
  get appointment_date() {
    return this.appointmentForm.get('appointment_date');
  }
  get notes() {
    return this.appointmentForm.get('notes');
  }
}
