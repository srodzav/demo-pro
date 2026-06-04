import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';

export interface Permission {
  id: number;
  name: string;
  label: string;
  group: string;
}

export interface Role {
  id: number;
  name: string;
  label: string;
  description?: string | null;
  is_system: boolean;
  permissions: Permission[];
}

export interface SettingsUser {
  id: number;
  name: string;
  email: string;
  role_id: number | null;
  is_active: boolean;
  created_at?: string;
  role?: Role | null;
}

export interface ClinicSettings {
  id?: number;
  clinic_name: string;
  legal_name?: string | null;
  email?: string | null;
  phone?: string | null;
  whatsapp?: string | null;
  address?: string | null;
  city?: string | null;
  state?: string | null;
  primary_color?: string | null;
  accent_color?: string | null;
  logo_url?: string | null;
  business_hours?: Record<string, string> | null;
  social_links?: Record<string, string> | null;
}

export interface CatalogItem {
  id?: number;
  type: string;
  name: string;
  label: string;
  description?: string | null;
  price?: number | null;
  duration_minutes?: number | null;
  color?: string | null;
  is_active: boolean;
  sort_order?: number | null;
}

export interface SettingsOverview {
  clinic: ClinicSettings;
  app_settings: {
    public_registration_enabled: boolean;
  };
  roles: Role[];
  permissions: Permission[];
  catalogs: CatalogItem[];
}

@Injectable({
  providedIn: 'root',
})
export class SettingsService {
  private apiUrl = `${environment.apiUrl}/settings`;

  constructor(private http: HttpClient) {}

  getOverview(): Observable<SettingsOverview> {
    return this.http.get<SettingsOverview>(this.apiUrl);
  }

  updateClinic(clinic: ClinicSettings): Observable<{ clinic: ClinicSettings }> {
    return this.http.put<{ clinic: ClinicSettings }>(`${this.apiUrl}/clinic`, clinic);
  }

  uploadClinicLogo(file: File): Observable<{ clinic: ClinicSettings }> {
    const formData = new FormData();
    formData.append('logo', file);

    return this.http.post<{ clinic: ClinicSettings }>(`${this.apiUrl}/clinic/logo`, formData);
  }

  updateAppSettings(settings: { public_registration_enabled: boolean }): Observable<{ app_settings: { public_registration_enabled: boolean } }> {
    return this.http.put<{ app_settings: { public_registration_enabled: boolean } }>(`${this.apiUrl}/app`, settings);
  }

  getUsers(): Observable<SettingsUser[]> {
    return this.http.get<SettingsUser[]>(`${this.apiUrl}/users`);
  }

  createUser(user: Partial<SettingsUser> & { password: string }): Observable<{ user: SettingsUser }> {
    return this.http.post<{ user: SettingsUser }>(`${this.apiUrl}/users`, user);
  }

  updateUser(id: number, user: Partial<SettingsUser> & { password?: string }): Observable<{ user: SettingsUser }> {
    return this.http.put<{ user: SettingsUser }>(`${this.apiUrl}/users/${id}`, user);
  }

  createRole(role: { name: string; label: string; description?: string; permission_ids: number[] }): Observable<{ role: Role }> {
    return this.http.post<{ role: Role }>(`${this.apiUrl}/roles`, role);
  }

  updateRole(id: number, role: { label?: string; description?: string | null; permission_ids?: number[] }): Observable<{ role: Role }> {
    return this.http.put<{ role: Role }>(`${this.apiUrl}/roles/${id}`, role);
  }

  createCatalog(catalog: CatalogItem): Observable<{ catalog: CatalogItem }> {
    return this.http.post<{ catalog: CatalogItem }>(`${this.apiUrl}/catalogs`, catalog);
  }

  updateCatalog(id: number, catalog: CatalogItem): Observable<{ catalog: CatalogItem }> {
    return this.http.put<{ catalog: CatalogItem }>(`${this.apiUrl}/catalogs/${id}`, catalog);
  }

  deleteCatalog(id: number): Observable<{ message: string }> {
    return this.http.delete<{ message: string }>(`${this.apiUrl}/catalogs/${id}`);
  }
}
