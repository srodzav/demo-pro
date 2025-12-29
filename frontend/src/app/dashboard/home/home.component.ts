import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { AppointmentService } from '../../services/appointment.service';

@Component({
  selector: 'app-home',
  standalone: true,
  imports: [CommonModule, RouterLink],
  templateUrl: './home.component.html',
  styleUrl: './home.component.scss',
})
export class HomeComponent implements OnInit {
  stats = {
    total: 0,
    pending: 0,
    confirmed: 0,
    today: 0,
  };

  recentAppointments: any[] = [];
  isLoading = true;

  constructor(private appointmentService: AppointmentService) {}

  ngOnInit(): void {
    this.loadDashboardData();
  }

  loadDashboardData(): void {
    this.appointmentService.getAll().subscribe({
      next: (response) => {
        const appointments = response.data || [];

        // Calculate stats
        this.stats.total = appointments.length;
        this.stats.pending = appointments.filter((a: any) => a.status === 'pending').length;
        this.stats.confirmed = appointments.filter((a: any) => a.status === 'confirmed').length;

        const today = new Date().toDateString();
        this.stats.today = appointments.filter((a: any) => {
          const apptDate = new Date(a.appointment_date).toDateString();
          return apptDate === today;
        }).length;

        // Get recent appointments
        this.recentAppointments = appointments.slice(0, 5);

        this.isLoading = false;
      },
      error: (error) => {
        console.error('Error loading dashboard:', error);
        this.isLoading = false;
      },
    });
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
