export interface PsgcLocation {
  code: string;
  name: string;
  regionName?: string;
  provinceCode?: string | false;
  regionCode?: string;
  isCity?: boolean;
  isMunicipality?: boolean;
}

export interface GeocodeResult {
  latitude: number;
  longitude: number;
  display_name: string;
}
