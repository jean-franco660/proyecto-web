/**
 * Constantes de ENUMs del dominio de negocio.
 * Centraliza los strings para evitar magic strings repetidos en las Views.
 * Los valores DEBEN coincidir exactamente con los ENUMs definidos en la BD (case-sensitive).
 */

export const ESTADO_DIARIO = {
  FALTA:       'FALTA',
  PRESENTE:    'PRESENTE',
  TARDANZA:    'TARDANZA',
  JUSTIFICADO: 'JUSTIFICADO',
  PENDIENTE:   'PENDIENTE',
}

export const ESTADO_USUARIO_APP = {
  ACTIVO:   'ACTIVO',
  INACTIVO: 'INACTIVO',
  BLOQUEADO: 'BLOQUEADO',
}

export const ESTADO_USUARIO_WEB = {
  ACTIVO:   'ACTIVO',
  INACTIVO: 'INACTIVO',
}

export const ROL_WEB = {
  ADMIN:      'administrador',
  SUPERVISOR: 'supervisor',
}

export const TIPO_JUSTIFICACION = {
  ENFERMEDAD:        'ENFERMEDAD',
  PERMISO_PERSONAL:  'PERMISO_PERSONAL',
  LICENCIA:          'LICENCIA',
  COMISION_SERVICIO: 'COMISION_SERVICIO',
  CAPACITACION:      'CAPACITACION',
  DUELO:             'DUELO',
  MATERNIDAD:        'MATERNIDAD',
  PATERNIDAD:        'PATERNIDAD',
  OLVIDO_MARCACION:  'OLVIDO_MARCACION',
  OTRO:              'OTRO',
}

/** Labels en español para mostrar en la UI */
export const TIPO_JUSTIFICACION_LABEL = {
  ENFERMEDAD:        'Enfermedad',
  PERMISO_PERSONAL:  'Permiso Personal',
  LICENCIA:          'Licencia',
  COMISION_SERVICIO: 'Comisión de Servicio',
  CAPACITACION:      'Capacitación',
  DUELO:             'Duelo',
  MATERNIDAD:        'Maternidad',
  PATERNIDAD:        'Paternidad',
  OLVIDO_MARCACION:  'Olvido de Marcación',
  OTRO:              'Otro',
}

export const ESTADO_JUSTIFICACION = {
  PENDIENTE: 'PENDIENTE',
  APROBADO:  'APROBADO',
  RECHAZADO: 'RECHAZADO',
}

export const ESTADO_MARCACION = {
  VALIDA:   'VALIDA',
  OBSERVADA: 'OBSERVADA',
}

export const ESTADO_REVISION = {
  PENDIENTE:          'PENDIENTE',
  APROBADA:           'APROBADA',
  MANTENER_OBSERVADA: 'MANTENER_OBSERVADA',
}

export const TIPO_FERIADO = {
  NACIONAL: 'NACIONAL',
  LOCAL:    'LOCAL',
  EMPRESA:  'EMPRESA',
}
