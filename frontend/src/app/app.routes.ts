import { Routes } from '@angular/router';
import { LandingComponent } from './pages/landing/landing.component';
import { LoginComponent } from './auth/login/login.component';
import { RegisterComponent } from './auth/register/register.component';
import { authGuard } from './guards/auth.guard';
import { LayoutComponent } from './dashboard/layout/layout.component';
import { HomeComponent } from './dashboard/home/home.component';
import { ListComponent } from './dashboard/appointments/list/list.component';
import { CreateComponent } from './dashboard/appointments/create/create.component';
import { EditComponent } from './dashboard/appointments/edit/edit.component';
import { ListComponent as PatientsListComponent } from './dashboard/patients/list/list.component';
import { DetailComponent as PatientDetailComponent } from './dashboard/patients/detail/detail.component';
import { WeeklyCalendarComponent } from './dashboard/weekly-calendar/weekly-calendar.component';
import { FinanceComponent } from './dashboard/finance/finance.component';
import { StatisticsComponent } from './dashboard/statistics/statistics.component';
import { SettingsComponent } from './dashboard/settings/settings.component';

export const routes: Routes = [
  // Public routes
  { path: '', component: LandingComponent },
  { path: 'login', component: LoginComponent },
  { path: 'register', component: RegisterComponent },

  // Protected routes (Dashboard)
  {
    path: 'dashboard',
    component: LayoutComponent,
    canActivate: [authGuard],
    children: [
      { path: '', component: HomeComponent },
      { path: 'calendario', component: WeeklyCalendarComponent },
      { path: 'citas', component: ListComponent },
      { path: 'citas/nueva', component: CreateComponent },
      { path: 'citas/editar/:id', component: EditComponent },
      { path: 'appointments/create', component: CreateComponent },
      { path: 'appointments/:id', component: EditComponent },
      { path: 'pacientes', component: PatientsListComponent },
      { path: 'pacientes/:id', component: PatientDetailComponent },
      { path: 'finanzas', component: FinanceComponent },
      { path: 'estadisticas', component: StatisticsComponent },
      { path: 'configuracion', component: SettingsComponent },
    ],
  },

  // Redirect unknown routes to home
  { path: '**', redirectTo: '' },
];
