import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';

export interface DashboardStats {
  patients: {
    total: number;
    new_this_month: number;
  };
  appointments: {
    total: number;
    this_month: number;
    upcoming: number;
    pending: number;
  };
  finance: {
    income_this_month: number;
    income_last_month: number;
    income_change_percentage: number;
    total_income: number;
    accounts_receivable: number;
  };
}

export interface MonthlyIncome {
  month: number;
  month_name: string;
  total: number;
}

export interface FinancialReport {
  period: {
    from: string;
    to: string;
  };
  income: {
    total: number;
    payment_count: number;
    average_ticket: number;
    by_type: any[];
    by_method: any[];
  };
  patients: {
    new: number;
  };
  payment_plans: {
    active: number;
    completed_in_period: number;
  };
  accounts_receivable: number;
}

@Injectable({
  providedIn: 'root'
})
export class StatisticsService {
  private apiUrl = `${environment.apiUrl}/statistics`;

  constructor(private http: HttpClient) {}

  /**
   * Get dashboard statistics
   */
  getDashboardStats(): Observable<DashboardStats> {
    return this.http.get<DashboardStats>(`${this.apiUrl}/dashboard`);
  }

  /**
   * Get monthly income for a specific year
   */
  getMonthlyIncome(year?: number): Observable<MonthlyIncome[]> {
    let params = new HttpParams();
    if (year) {
      params = params.set('year', year.toString());
    }
    return this.http.get<MonthlyIncome[]>(`${this.apiUrl}/monthly-income`, { params });
  }

  /**
   * Get daily income for a specific month
   */
  getDailyIncome(month?: number, year?: number): Observable<any[]> {
    let params = new HttpParams();
    if (month) params = params.set('month', month.toString());
    if (year) params = params.set('year', year.toString());
    return this.http.get<any[]>(`${this.apiUrl}/daily-income`, { params });
  }

  /**
   * Get treatment statistics
   */
  getTreatmentStats(fromDate?: string, toDate?: string): Observable<any[]> {
    let params = new HttpParams();
    if (fromDate) params = params.set('from_date', fromDate);
    if (toDate) params = params.set('to_date', toDate);
    return this.http.get<any[]>(`${this.apiUrl}/treatment-stats`, { params });
  }

  /**
   * Get appointment statistics
   */
  getAppointmentStats(fromDate?: string, toDate?: string): Observable<any> {
    let params = new HttpParams();
    if (fromDate) params = params.set('from_date', fromDate);
    if (toDate) params = params.set('to_date', toDate);
    return this.http.get<any>(`${this.apiUrl}/appointment-stats`, { params });
  }

  /**
   * Get payment method statistics
   */
  getPaymentMethodStats(fromDate?: string, toDate?: string): Observable<any[]> {
    let params = new HttpParams();
    if (fromDate) params = params.set('from_date', fromDate);
    if (toDate) params = params.set('to_date', toDate);
    return this.http.get<any[]>(`${this.apiUrl}/payment-method-stats`, { params });
  }

  /**
   * Get accounts receivable details
   */
  getAccountsReceivable(): Observable<any> {
    return this.http.get<any>(`${this.apiUrl}/accounts-receivable`);
  }

  /**
   * Get top patients by spending
   */
  getTopPatients(limit: number = 10): Observable<any[]> {
    const params = new HttpParams().set('limit', limit.toString());
    return this.http.get<any[]>(`${this.apiUrl}/top-patients`, { params });
  }

  /**
   * Get peak hours for appointments
   */
  getPeakHours(fromDate?: string, toDate?: string): Observable<any[]> {
    let params = new HttpParams();
    if (fromDate) params = params.set('from_date', fromDate);
    if (toDate) params = params.set('to_date', toDate);
    return this.http.get<any[]>(`${this.apiUrl}/peak-hours`, { params });
  }

  /**
   * Get busiest weekdays for appointments
   */
  getPeakDays(fromDate?: string, toDate?: string): Observable<any[]> {
    let params = new HttpParams();
    if (fromDate) params = params.set('from_date', fromDate);
    if (toDate) params = params.set('to_date', toDate);
    return this.http.get<any[]>(`${this.apiUrl}/peak-days`, { params });
  }

  /**
   * Get comprehensive financial report
   */
  getFinancialReport(fromDate?: string, toDate?: string): Observable<FinancialReport> {
    let params = new HttpParams();
    if (fromDate) params = params.set('from_date', fromDate);
    if (toDate) params = params.set('to_date', toDate);
    return this.http.get<FinancialReport>(`${this.apiUrl}/financial-report`, { params });
  }
}
