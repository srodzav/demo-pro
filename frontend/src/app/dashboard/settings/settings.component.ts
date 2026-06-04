import { CommonModule } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import {
  CatalogItem,
  ClinicSettings,
  Permission,
  Role,
  SettingsService,
  SettingsUser,
} from '../../services/settings.service';

type SettingsTab = 'clinic' | 'access' | 'users' | 'roles' | 'catalogs';

@Component({
  selector: 'app-settings',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './settings.component.html',
  styleUrl: './settings.component.scss',
})
export class SettingsComponent implements OnInit {
  activeTab: SettingsTab = 'clinic';
  loading = true;
  saving = false;
  message = '';
  errorMessage = '';

  clinic: ClinicSettings = this.emptyClinic();
  publicRegistrationEnabled = false;
  users: SettingsUser[] = [];
  roles: Role[] = [];
  permissions: Permission[] = [];
  catalogs: CatalogItem[] = [];

  newUser = {
    name: '',
    email: '',
    password: '',
    role_id: 0,
    is_active: true,
  };

  newRole = {
    label: '',
    description: '',
    permission_ids: [] as number[],
  };

  newCatalog: CatalogItem = this.emptyCatalog();

  catalogTypes = [
    { value: 'treatment', label: 'Tratamientos', description: 'Servicios que aparecen al crear citas y solicitudes publicas.' },
    { value: 'payment_method', label: 'Metodos de pago', description: 'Opciones disponibles al registrar pagos.' },
    { value: 'appointment_status', label: 'Estados de cita', description: 'Estados operativos para seguimiento de agenda.' },
    { value: 'record_type', label: 'Tipos de expediente', description: 'Clasificacion de notas clinicas.' },
  ];

  businessDays = [
    { key: 'monday', label: 'Lunes' },
    { key: 'tuesday', label: 'Martes' },
    { key: 'wednesday', label: 'Miercoles' },
    { key: 'thursday', label: 'Jueves' },
    { key: 'friday', label: 'Viernes' },
    { key: 'saturday', label: 'Sabado' },
    { key: 'sunday', label: 'Domingo' },
  ];

  constructor(private settingsService: SettingsService) {}

  ngOnInit(): void {
    this.loadSettings();
  }

  loadSettings(): void {
    this.loading = true;
    this.settingsService.getOverview().subscribe({
      next: (overview) => {
        this.clinic = {
          ...this.emptyClinic(),
          ...overview.clinic,
          business_hours: overview.clinic.business_hours || this.emptyBusinessHours(),
          social_links: overview.clinic.social_links || {},
        };
        this.publicRegistrationEnabled = overview.app_settings.public_registration_enabled;
        this.roles = overview.roles;
        this.permissions = overview.permissions;
        this.catalogs = overview.catalogs;
        this.loadUsers();
      },
      error: () => {
        this.loading = false;
        this.errorMessage = 'No se pudo cargar la configuracion.';
      },
    });
  }

  loadUsers(): void {
    this.settingsService.getUsers().subscribe({
      next: (users) => {
        this.users = users;
        this.newUser.role_id = this.roles[0]?.id || 0;
        this.loading = false;
      },
      error: () => {
        this.loading = false;
        this.errorMessage = 'No se pudo cargar la lista de usuarios.';
      },
    });
  }

  setTab(tab: SettingsTab): void {
    this.activeTab = tab;
    this.clearMessages();
  }

  saveClinic(): void {
    this.save(() => {
      this.settingsService.updateClinic(this.clinic).subscribe({
        next: ({ clinic }) => {
          this.clinic = clinic;
          this.finishSave('Configuracion de clinica actualizada.');
        },
        error: () => this.failSave('No se pudo actualizar la clinica.'),
      });
    });
  }

  uploadLogo(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    if (!file) return;

    this.save(() => {
      this.settingsService.uploadClinicLogo(file).subscribe({
        next: ({ clinic }) => {
          this.clinic = {
            ...this.clinic,
            ...clinic,
            business_hours: clinic.business_hours || this.clinic.business_hours,
            social_links: clinic.social_links || this.clinic.social_links,
          };
          input.value = '';
          this.finishSave('Logo actualizado.');
        },
        error: () => this.failSave('No se pudo subir el logo. Usa JPG, PNG, WEBP o SVG menor a 2 MB.'),
      });
    });
  }

  saveAccess(): void {
    this.save(() => {
      this.settingsService
        .updateAppSettings({ public_registration_enabled: this.publicRegistrationEnabled })
        .subscribe({
          next: ({ app_settings }) => {
            this.publicRegistrationEnabled = app_settings.public_registration_enabled;
            this.finishSave('Configuracion de acceso actualizada.');
          },
          error: () => this.failSave('No se pudo actualizar la configuracion de acceso.'),
        });
    });
  }

  createUser(): void {
    if (!this.newUser.role_id) {
      this.errorMessage = 'Selecciona un rol para el usuario.';
      return;
    }

    this.save(() => {
      this.settingsService.createUser(this.newUser).subscribe({
        next: ({ user }) => {
          this.users = [...this.users, user].sort((a, b) => a.name.localeCompare(b.name));
          this.newUser = { name: '', email: '', password: '', role_id: this.roles[0]?.id || 0, is_active: true };
          this.finishSave('Usuario creado.');
        },
        error: (error) => this.failSave(error.error?.message || 'No se pudo crear el usuario.'),
      });
    });
  }

  updateUser(user: SettingsUser): void {
    if (!user.id) return;

    this.save(() => {
      this.settingsService.updateUser(user.id, {
        name: user.name,
        email: user.email,
        role_id: user.role_id,
        is_active: user.is_active,
      }).subscribe({
        next: ({ user: updatedUser }) => {
          this.users = this.users.map((item) => (item.id === updatedUser.id ? updatedUser : item));
          this.finishSave('Usuario actualizado.');
        },
        error: () => this.failSave('No se pudo actualizar el usuario.'),
      });
    });
  }

  createRole(): void {
    const rolePayload = {
      ...this.newRole,
      name: this.slugify(this.newRole.label),
    };

    this.save(() => {
      this.settingsService.createRole(rolePayload).subscribe({
        next: ({ role }) => {
          this.roles = [...this.roles, role].sort((a, b) => a.label.localeCompare(b.label));
          this.newRole = { label: '', description: '', permission_ids: [] };
          this.finishSave('Rol creado.');
        },
        error: (error) => this.failSave(error.error?.message || 'No se pudo crear el rol.'),
      });
    });
  }

  updateRole(role: Role): void {
    this.save(() => {
      this.settingsService.updateRole(role.id, {
        label: role.label,
        description: role.description,
        permission_ids: role.permissions.map((permission) => permission.id),
      }).subscribe({
        next: ({ role: updatedRole }) => {
          this.roles = this.roles.map((item) => (item.id === updatedRole.id ? updatedRole : item));
          this.finishSave('Rol actualizado.');
        },
        error: () => this.failSave('No se pudo actualizar el rol.'),
      });
    });
  }

  createCatalog(): void {
    this.save(() => {
      this.settingsService.createCatalog(this.normalizeCatalog(this.newCatalog)).subscribe({
        next: ({ catalog }) => {
          this.catalogs = [...this.catalogs, catalog].sort(this.sortCatalogs);
          this.newCatalog = this.emptyCatalog();
          this.finishSave('Catalogo creado.');
        },
        error: (error) => this.failSave(error.error?.message || 'No se pudo crear el catalogo.'),
      });
    });
  }

  updateCatalog(catalog: CatalogItem): void {
    if (!catalog.id) return;
    const catalogId = catalog.id;

    this.save(() => {
      this.settingsService.updateCatalog(catalogId, this.normalizeCatalog(catalog)).subscribe({
        next: ({ catalog: updatedCatalog }) => {
          this.catalogs = this.catalogs.map((item) => (item.id === updatedCatalog.id ? updatedCatalog : item)).sort(this.sortCatalogs);
          this.finishSave('Catalogo actualizado.');
        },
        error: () => this.failSave('No se pudo actualizar el catalogo.'),
      });
    });
  }

  deleteCatalog(catalog: CatalogItem): void {
    if (!catalog.id || !confirm(`Eliminar ${catalog.label}?`)) return;

    this.save(() => {
      this.settingsService.deleteCatalog(catalog.id as number).subscribe({
        next: () => {
          this.catalogs = this.catalogs.filter((item) => item.id !== catalog.id);
          this.finishSave('Catalogo eliminado.');
        },
        error: () => this.failSave('No se pudo eliminar el catalogo.'),
      });
    });
  }

  groupedPermissions(): { group: string; permissions: Permission[] }[] {
    const groups = new Map<string, Permission[]>();
    this.permissions.forEach((permission) => {
      const items = groups.get(permission.group) || [];
      items.push(permission);
      groups.set(permission.group, items);
    });

    return Array.from(groups.entries()).map(([group, permissions]) => ({ group, permissions }));
  }

  catalogsByType(type: string): CatalogItem[] {
    return this.catalogs.filter((catalog) => catalog.type === type);
  }

  catalogTypeDescription(type: string): string {
    return this.catalogTypes.find((catalogType) => catalogType.value === type)?.description || '';
  }

  showCatalogPrice(type: string): boolean {
    return type === 'treatment';
  }

  showCatalogColor(type: string): boolean {
    return type === 'appointment_status';
  }

  roleHasPermission(role: Role, permissionId: number): boolean {
    return role.permissions.some((permission) => permission.id === permissionId);
  }

  toggleRolePermission(role: Role, permission: Permission, checked: boolean): void {
    if (checked && !this.roleHasPermission(role, permission.id)) {
      role.permissions = [...role.permissions, permission];
    }

    if (!checked) {
      role.permissions = role.permissions.filter((item) => item.id !== permission.id);
    }
  }

  newRoleHasPermission(permissionId: number): boolean {
    return this.newRole.permission_ids.includes(permissionId);
  }

  toggleNewRolePermission(permissionId: number, checked: boolean): void {
    if (checked && !this.newRole.permission_ids.includes(permissionId)) {
      this.newRole.permission_ids = [...this.newRole.permission_ids, permissionId];
    }

    if (!checked) {
      this.newRole.permission_ids = this.newRole.permission_ids.filter((id) => id !== permissionId);
    }
  }

  trackById(_: number, item: { id?: number }): number | undefined {
    return item.id;
  }

  private save(action: () => void): void {
    this.clearMessages();
    this.saving = true;
    action();
  }

  private finishSave(message: string): void {
    this.saving = false;
    this.message = message;
  }

  private failSave(message: string): void {
    this.saving = false;
    this.errorMessage = message;
  }

  private clearMessages(): void {
    this.message = '';
    this.errorMessage = '';
  }

  private normalizeCatalog(catalog: CatalogItem): CatalogItem {
    const generatedName = catalog.name || this.slugify(catalog.label);

    return {
      ...catalog,
      name: generatedName,
      price: catalog.price === undefined ? null : catalog.price,
      duration_minutes: null,
      sort_order: catalog.sort_order || 0,
    };
  }

  private sortCatalogs(a: CatalogItem, b: CatalogItem): number {
    return a.type.localeCompare(b.type) || (a.sort_order || 0) - (b.sort_order || 0) || a.label.localeCompare(b.label);
  }

  private slugify(value: string): string {
    return value
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
      .trim()
      .replace(/[^a-z0-9]+/g, '_')
      .replace(/^_+|_+$/g, '');
  }

  private emptyClinic(): ClinicSettings {
    return {
      clinic_name: '',
      legal_name: '',
      email: '',
      phone: '',
      whatsapp: '',
      address: '',
      city: '',
      state: '',
      primary_color: '#003c97',
      accent_color: '#007bff',
      logo_url: '',
      business_hours: this.emptyBusinessHours(),
      social_links: {},
    };
  }

  private emptyBusinessHours(): Record<string, string> {
    return {
      monday: '',
      tuesday: '',
      wednesday: '',
      thursday: '',
      friday: '',
      saturday: '',
      sunday: '',
    };
  }

  private emptyCatalog(): CatalogItem {
    return {
      type: 'treatment',
      name: '',
      label: '',
      description: '',
      price: null,
      duration_minutes: null,
      color: '#003c97',
      is_active: true,
      sort_order: 0,
    };
  }
}
