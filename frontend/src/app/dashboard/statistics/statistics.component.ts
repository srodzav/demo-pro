import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { StatisticsService } from '../../services/statistics.service';
import { RouterModule } from '@angular/router';
import { firstValueFrom } from 'rxjs';

interface AppointmentStats {
  total: number;
  confirmed: number;
  pending: number;
  cancelled: number;
  completed: number;
  cancellation_rate: number;
}

interface TreatmentStat {
  treatment: string;
  count: number;
  total_revenue: number;
  percentage: number;
}

interface TopPatient {
  patient_id: number;
  patient_name: string;
  total_appointments: number;
  total_spent: number;
  last_appointment: string;
}

interface PeakHour {
  hour: number;
  count: number;
  percentage: number;
}

interface PeakDay {
  day: number;
  day_name: string;
  count: number;
  percentage: number;
}

interface FinancialSummary {
  income: number;
  average_ticket: number;
  payment_count: number;
  new_patients: number;
  accounts_receivable: number;
}

@Component({
  selector: 'app-statistics',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './statistics.component.html',
  styleUrl: './statistics.component.scss'
})
export class StatisticsComponent implements OnInit {
  loading = true;
  
  // General stats
  appointmentStats: AppointmentStats = {
    total: 0,
    confirmed: 0,
    pending: 0,
    cancelled: 0,
    completed: 0,
    cancellation_rate: 0
  };
  
  // Treatment stats
  treatmentStats: TreatmentStat[] = [];
  
  // Top patients
  topPatients: TopPatient[] = [];
  
  // Peak hours
  peakHours: PeakHour[] = [];
  peakDays: PeakDay[] = [];

  financialSummary: FinancialSummary = {
    income: 0,
    average_ticket: 0,
    payment_count: 0,
    new_patients: 0,
    accounts_receivable: 0
  };
  
  // Date filters
  startDate = '';
  endDate = '';
  
  // Selected period
  selectedPeriod = 'month';
  periods = [
    { value: 'week', label: 'Última Semana' },
    { value: 'month', label: 'Último Mes' },
    { value: 'quarter', label: 'Último Trimestre' },
    { value: 'year', label: 'Último Año' },
    { value: 'custom', label: 'Personalizado' }
  ];

  constructor(private statisticsService: StatisticsService) {
    this.initializeDates();
  }

  ngOnInit(): void {
    this.loadStatistics();
  }

  initializeDates(): void {
    const today = new Date();
    const lastMonth = new Date(today);
    lastMonth.setMonth(today.getMonth() - 1);
    
    this.endDate = this.formatDateForInput(today);
    this.startDate = this.formatDateForInput(lastMonth);
  }

  formatDateForInput(date: Date): string {
    return date.toISOString().split('T')[0];
  }

  loadStatistics(): void {
    this.loading = true;
    
    Promise.all([
      this.loadAppointmentStats(),
      this.loadTreatmentStats(),
      this.loadTopPatients(),
      this.loadPeakHours(),
      this.loadPeakDays(),
      this.loadFinancialSummary()
    ]).finally(() => {
      this.loading = false;
    });
  }

  async loadAppointmentStats(): Promise<void> {
    try {
      const data = await firstValueFrom(this.statisticsService.getAppointmentStats(
        this.startDate,
        this.endDate
      ));
      this.appointmentStats = data;
    } catch (error) {
      console.error('Error loading appointment stats:', error);
    }
  }

  async loadTreatmentStats(): Promise<void> {
    try {
      const data = await firstValueFrom(this.statisticsService.getTreatmentStats(
        this.startDate,
        this.endDate
      ));
      this.treatmentStats = data || [];
    } catch (error) {
      console.error('Error loading treatment stats:', error);
    }
  }

  async loadTopPatients(): Promise<void> {
    try {
      const data = await firstValueFrom(this.statisticsService.getTopPatients(10));
      this.topPatients = data || [];
    } catch (error) {
      console.error('Error loading top patients:', error);
    }
  }

  async loadPeakHours(): Promise<void> {
    try {
      const data = await firstValueFrom(this.statisticsService.getPeakHours(
        this.startDate,
        this.endDate
      ));
      this.peakHours = data || [];
    } catch (error) {
      console.error('Error loading peak hours:', error);
    }
  }

  async loadPeakDays(): Promise<void> {
    try {
      const data = await firstValueFrom(this.statisticsService.getPeakDays(
        this.startDate,
        this.endDate
      ));
      this.peakDays = data || [];
    } catch (error) {
      console.error('Error loading peak days:', error);
    }
  }

  async loadFinancialSummary(): Promise<void> {
    try {
      const data = await firstValueFrom(this.statisticsService.getFinancialReport(
        this.startDate,
        this.endDate
      ));
      this.financialSummary = {
        income: data.income?.total || 0,
        average_ticket: data.income?.average_ticket || 0,
        payment_count: data.income?.payment_count || 0,
        new_patients: data.patients?.new || 0,
        accounts_receivable: data.accounts_receivable || 0
      };
    } catch (error) {
      console.error('Error loading financial summary:', error);
    }
  }

  onPeriodChange(): void {
    if (this.selectedPeriod === 'custom') {
      return; // Wait for user to select dates
    }
    
    const today = new Date();
    let startDate = new Date(today);
    
    switch (this.selectedPeriod) {
      case 'week':
        startDate.setDate(today.getDate() - 7);
        break;
      case 'month':
        startDate.setMonth(today.getMonth() - 1);
        break;
      case 'quarter':
        startDate.setMonth(today.getMonth() - 3);
        break;
      case 'year':
        startDate.setFullYear(today.getFullYear() - 1);
        break;
    }
    
    startDate.setHours(0, 0, 0, 0);
    today.setHours(0, 0, 0, 0);

    this.startDate = this.formatDateForInput(startDate);
    this.endDate = this.formatDateForInput(today);
    
    this.loadStatistics();
  }

  onDateChange(): void {
    if (this.startDate && this.endDate) {
      this.selectedPeriod = 'custom';
      this.loadStatistics();
    }
  }

  formatCurrency(value: number): string {
    return new Intl.NumberFormat('es-MX', {
      style: 'currency',
      currency: 'MXN'
    }).format(value);
  }

  formatDate(dateString: string): string {
    if (!dateString) {
      return 'Sin visitas';
    }

    const date = new Date(dateString);
    return date.toLocaleDateString('es-MX', {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    });
  }

  formatHour(hour: number): string {
    const period = hour >= 12 ? 'PM' : 'AM';
    const displayHour = hour > 12 ? hour - 12 : hour === 0 ? 12 : hour;
    return `${displayHour}:00 ${period}`;
  }

  getStatusPercentage(value: number): number {
    if (this.appointmentStats.total === 0) return 0;
    return (value / this.appointmentStats.total) * 100;
  }

  getMaxPeakHourCount(): number {
    if (this.peakHours.length === 0) return 0;
    return Math.max(...this.peakHours.map(h => h.count));
  }

  getMaxPeakDayCount(): number {
    if (this.peakDays.length === 0) return 0;
    return Math.max(...this.peakDays.map(day => day.count));
  }

  getBarHeight(count: number): number {
    const max = this.getMaxPeakHourCount();
    if (max === 0) return 0;
    return 12 + ((count / max) * 76);
  }

  getDayBarWidth(count: number): number {
    const max = this.getMaxPeakDayCount();
    if (max === 0) return 0;
    return 8 + ((count / max) * 92);
  }
}
