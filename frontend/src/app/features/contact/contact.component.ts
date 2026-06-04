import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { AppointmentService } from '../../services/appointment.service';

@Component({
  selector: 'app-contact',
  standalone: true,
  imports: [CommonModule, RouterModule, FormsModule],
  templateUrl: './contact.component.html',
  styleUrl: './contact.component.scss',
})
export class ContactComponent {
  constructor(private appointmentService: AppointmentService) {}
  formData = {
    name: '',
    phone: '',
    email: '',
    treatment: 'consulta_general',
    message: '',
    appointment_date: '',
  };

  treatments = [
    { value: 'consulta_general', label: 'Consulta General' },
    { value: 'ortodoncia', label: 'Ortodoncia' },
    { value: 'brackets', label: 'Brackets' },
  ];

  isSubmitting = false;
  showSuccessMessage = false;
  showErrorMessage = false;

  schedule = [
    { day: 'Lunes - Viernes', hours: '9:00 AM - 7:00 PM' },
    { day: 'Sábados', hours: '9:00 AM - 2:00 PM' },
    { day: 'Domingos', hours: 'Cerrado' },
  ];

  contactInfo = [
    {
      icon: 'location_on',
      title: 'Dirección',
      content: 'Calle Independencia 1189, Centro Historico, Col. Centro',
      subContent: 'San Luis Potosí, S.L.P. 78000',
      link: 'https://maps.google.com',
    },
    {
      icon: 'call',
      title: 'Teléfono',
      content: '444 312 2257',
      subContent: 'WhatsApp disponible',
      link: 'tel:+4443122257',
    },
    {
      icon: 'email',
      title: 'Email',
      content: 'contact@sebastianrdz.com',
      subContent: 'Respuesta en 24 horas',
      link: 'mailto:contact@sebastianrdz.com',
    },
  ];

  onSubmit() {
    if (this.isSubmitting) return;

    this.isSubmitting = true;
    this.showSuccessMessage = false;
    this.showErrorMessage = false;

    const appointmentData = {
      patient_name: this.formData.name,
      patient_email: this.formData.email,
      patient_phone: this.formData.phone,
      treatment_type: this.formData.treatment,
      notes: this.formData.message,
      // appointment_date: this.formData.appointment_date, // Ignore 
    };

    this.appointmentService.createPublic(appointmentData).subscribe({
      next: (response) => {
        this.showSuccessMessage = true;
        this.resetForm();
        this.isSubmitting = false;
        setTimeout(() => {
          this.showSuccessMessage = false;
        }, 5000);
      },
      error: (error) => {
        console.error('Error al enviar solicitud:', error);
        this.showErrorMessage = true;
        this.isSubmitting = false;
        setTimeout(() => {
          this.showErrorMessage = false;
        }, 5000);
      },
    });
  }

  resetForm() {
    this.formData = {
      name: '',
      phone: '',
      email: '',
      treatment: 'consulta_general',
      message: '',
      appointment_date: '',
    };
  }
}
