import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';

export interface Appointment {
  id?: number;
  user_id?: number | null;
  patient_name: string;
  patient_email: string;
  patient_phone: string;
  treatment_type: string;
  appointment_date?: string;
  notes?: string;
  status?: 'pending' | 'confirmed' | 'cancelled' | 'completed';
  google_calendar_event_id?: string | null;
  created_at?: string;
  updated_at?: string;
  user?: any;
}

export interface AppointmentFilters {
  status?: string;
  from_date?: string;
  to_date?: string;
}

@Injectable({
  providedIn: 'root',
})
export class AppointmentService {
  private apiUrl = 'http://localhost:8000/api/appointments';

  constructor(private http: HttpClient) {}

  /**
   * Get all appointments (admin only)
   */
  getAll(filters?: AppointmentFilters): Observable<any> {
    let params = new HttpParams();

    if (filters?.status) {
      params = params.set('status', filters.status);
    }
    if (filters?.from_date) {
      params = params.set('from_date', filters.from_date);
    }
    if (filters?.to_date) {
      params = params.set('to_date', filters.to_date);
    }

    return this.http.get(this.apiUrl, { params });
  }

  /**
   * Get appointment by ID
   */
  getById(id: number): Observable<{ appointment: Appointment }> {
    return this.http.get<{ appointment: Appointment }>(`${this.apiUrl}/${id}`);
  }

  /**
   * Create appointment (admin only - requires auth)
   */
  create(appointment: Appointment): Observable<any> {
    return this.http.post(this.apiUrl, appointment);
  }

  /**
   * Create appointment from public form (no auth required)
   */
  createPublic(appointment: Appointment): Observable<any> {
    return this.http.post(`${this.apiUrl}/public`, appointment);
  }

  /**
   * Update appointment
   */
  update(id: number, appointment: Partial<Appointment>): Observable<any> {
    return this.http.put(`${this.apiUrl}/${id}`, appointment);
  }

  /**
   * Delete appointment
   */
  delete(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/${id}`);
  }

  /**
   * Confirm appointment
   */
  confirm(id: number): Observable<any> {
    return this.http.post(`${this.apiUrl}/${id}/confirm`, {});
  }

  /**
   * Cancel appointment
   */
  cancel(id: number): Observable<any> {
    return this.http.post(`${this.apiUrl}/${id}/cancel`, {});
  }

  /**
   * Reschedule appointment
   */
  reschedule(id: number, newDate: string): Observable<any> {
    return this.http.post(`${this.apiUrl}/${id}/reschedule`, {
      appointment_date: newDate,
    });
  }
}
