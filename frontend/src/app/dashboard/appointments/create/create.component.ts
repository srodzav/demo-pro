import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, Validators, ReactiveFormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AppointmentService } from '../../../services/appointment.service';
import { PatientService, Patient } from '../../../services/patient.service';
import { debounceTime, distinctUntilChanged } from 'rxjs/operators';

@Component({
  selector: 'app-create',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterLink],
  templateUrl: './create.component.html',
  styleUrl: './create.component.scss',
})
export class CreateComponent implements OnInit {
  appointmentForm: FormGroup;
  isLoading = false;
  errorMessage = '';

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
  ];

  constructor(private fb: FormBuilder, private appointmentService: AppointmentService, private patientService: PatientService, private router: Router) {
    this.appointmentForm = this.fb.group({
      patient_name: ['', [Validators.required, Validators.minLength(3)]],
      patient_email: ['', [Validators.required, Validators.email]],
      patient_phone: ['', [Validators.required, Validators.pattern(/^\d{10}$/)]],
      treatment_type: ['', Validators.required],
      appointment_date: ['', Validators.required],
      notes: [''],
    });
  }

  ngOnInit(): void {
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
    if (this.appointmentForm.invalid) {
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

    this.appointmentService.create(appointmentData).subscribe({
      next: (response) => {
        console.log('Cita creada:', response);
        this.router.navigate(['/dashboard/citas']);
      },
      error: (error) => {
        console.error('Error:', error);
        this.errorMessage = error.error?.message || 'Error al crear la cita';
        this.isLoading = false;
      },
      complete: () => {
        this.isLoading = false;
      },
    });
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
