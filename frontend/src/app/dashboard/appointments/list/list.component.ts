import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { AppointmentService, Appointment } from '../../../services/appointment.service';

@Component({
  selector: 'app-list',
  standalone: true,
  imports: [CommonModule, RouterLink, FormsModule],
  templateUrl: './list.component.html',
  styleUrl: './list.component.scss',
})
export class ListComponent implements OnInit {
  appointments: Appointment[] = [];
  filteredAppointments: Appointment[] = [];
  isLoading = true;

  // Filters
  statusFilter = 'all';
  searchTerm = '';

  constructor(private appointmentService: AppointmentService) {}

  ngOnInit(): void {
    this.loadAppointments();
  }

  loadAppointments(): void {
    this.isLoading = true;
    this.appointmentService.getAll().subscribe({
      next: (response) => {
        this.appointments = response.data || [];
        this.applyFilters();
        this.isLoading = false;
      },
      error: (error) => {
        console.error('Error loading appointments:', error);
        this.isLoading = false;
      },
    });
  }

  applyFilters(): void {
    this.filteredAppointments = this.appointments.filter((appointment) => {
      // Status filter
      const matchesStatus = this.statusFilter === 'all' || appointment.status === this.statusFilter;

      // Search filter
      const matchesSearch =
        this.searchTerm === '' ||
        appointment.patient_name.toLowerCase().includes(this.searchTerm.toLowerCase()) ||
        appointment.patient_email.toLowerCase().includes(this.searchTerm.toLowerCase()) ||
        appointment.patient_phone.includes(this.searchTerm);

      return matchesStatus && matchesSearch;
    });
  }

  onFilterChange(): void {
    this.applyFilters();
  }

  confirmAppointment(id: number): void {
    if (confirm('¿Confirmar esta cita?')) {
      this.appointmentService.confirm(id).subscribe({
        next: () => {
          this.loadAppointments();
        },
        error: (error) => console.error('Error:', error),
      });
    }
  }

  cancelAppointment(id: number): void {
    if (confirm('¿Cancelar esta cita?')) {
      this.appointmentService.cancel(id).subscribe({
        next: () => {
          this.loadAppointments();
        },
        error: (error) => console.error('Error:', error),
      });
    }
  }

  deleteAppointment(id: number): void {
    if (confirm('¿Estás seguro de eliminar esta cita? Esta acción no se puede deshacer.')) {
      this.appointmentService.delete(id).subscribe({
        next: () => {
          this.loadAppointments();
        },
        error: (error) => console.error('Error:', error),
      });
    }
  }

  getStatusClass(status: string): string {
    const classes: any = {
      pending: 'status-pending',
      confirmed: 'status-confirmed',
      cancelled: 'status-cancelled',
      completed: 'status-completed',
    };
    return classes[status] || '';
  }

  getStatusText(status: string): string {
    const texts: any = {
      pending: 'Pendiente',
      confirmed: 'Confirmada',
      cancelled: 'Cancelada',
      completed: 'Completada',
    };
    return texts[status] || status;
  }

  getTreatmentText(type: string): string {
    const treatments: any = {
      consulta_general: 'Consulta General',
      brackets: 'Brackets General',
      brackets_metalicos: 'Brackets Metálicos',
      brackets_esteticos: 'Brackets Estéticos',
      ortodoncia: 'Ortodoncia General',
      ortodoncia_invisible: 'Ortodoncia Invisible',
      ortodoncia_infantil: 'Ortodoncia Infantil',
    };
    return treatments[type] || type;
  }
}
