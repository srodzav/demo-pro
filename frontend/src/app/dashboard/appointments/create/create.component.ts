import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, Validators, ReactiveFormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AppointmentService } from '../../../services/appointment.service';

@Component({
  selector: 'app-create',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterLink],
  templateUrl: './create.component.html',
  styleUrl: './create.component.scss',
})
export class CreateComponent {
  appointmentForm: FormGroup;
  isLoading = false;
  errorMessage = '';

  treatments = [
    { value: 'brackets_metalicos', label: 'Brackets Metálicos' },
    { value: 'brackets_esteticos', label: 'Brackets Estéticos' },
    { value: 'ortodoncia_invisible', label: 'Ortodoncia Invisible' },
    { value: 'ortodoncia_infantil', label: 'Ortodoncia Infantil' },
  ];

  constructor(private fb: FormBuilder, private appointmentService: AppointmentService, private router: Router) {
    this.appointmentForm = this.fb.group({
      patient_name: ['', [Validators.required, Validators.minLength(3)]],
      patient_email: ['', [Validators.required, Validators.email]],
      patient_phone: ['', [Validators.required, Validators.pattern(/^\d{10}$/)]],
      treatment_type: ['', Validators.required],
      appointment_date: ['', Validators.required],
      notes: [''],
    });
  }

  onSubmit(): void {
    if (this.appointmentForm.invalid) {
      this.appointmentForm.markAllAsTouched();
      return;
    }

    this.isLoading = true;
    this.errorMessage = '';

    this.appointmentService.create(this.appointmentForm.value).subscribe({
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
