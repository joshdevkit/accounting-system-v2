export type UomCategory =
    | 'count'
    | 'packaging'
    | 'weight'
    | 'volume'
    | 'length'
    | 'area'
    | 'energy'
    | 'time'
    | 'service';

export interface Uom {
    /** Stored on purchase_lines.uom */
    value: string;
    label: string;
    category: UomCategory;
}

export const UOMS = [
    // Count
    { value: 'PC', label: 'Piece', category: 'count' },
    { value: 'PAIR', label: 'Pair', category: 'count' },
    { value: 'SET', label: 'Set', category: 'count' },
    { value: 'DOZ', label: 'Dozen', category: 'count' },
    { value: 'UNIT', label: 'Unit', category: 'count' },
    { value: 'LOT', label: 'Lot', category: 'count' },
    // Packaging
    { value: 'BOX', label: 'Box', category: 'packaging' },
    { value: 'PACK', label: 'Pack', category: 'packaging' },
    { value: 'CTN', label: 'Carton', category: 'packaging' },
    { value: 'CASE', label: 'Case', category: 'packaging' },
    { value: 'BAG', label: 'Bag', category: 'packaging' },
    { value: 'SACK', label: 'Sack', category: 'packaging' },
    { value: 'BNDL', label: 'Bundle', category: 'packaging' },
    { value: 'BTL', label: 'Bottle', category: 'packaging' },
    { value: 'CAN', label: 'Can', category: 'packaging' },
    { value: 'JAR', label: 'Jar', category: 'packaging' },
    { value: 'TUBE', label: 'Tube', category: 'packaging' },
    { value: 'TRAY', label: 'Tray', category: 'packaging' },
    { value: 'SACHET', label: 'Sachet', category: 'packaging' },
    { value: 'ROLL', label: 'Roll', category: 'packaging' },
    { value: 'REAM', label: 'Ream', category: 'packaging' },
    { value: 'SHEET', label: 'Sheet', category: 'packaging' },
    { value: 'PAIL', label: 'Pail', category: 'packaging' },
    { value: 'DRUM', label: 'Drum', category: 'packaging' },
    { value: 'CYL', label: 'Cylinder', category: 'packaging' },
    // Weight
    { value: 'G', label: 'Gram', category: 'weight' },
    { value: 'KG', label: 'Kilogram', category: 'weight' },
    { value: 'LB', label: 'Pound', category: 'weight' },
    { value: 'OZ', label: 'Ounce', category: 'weight' },
    // Volume
    { value: 'ML', label: 'Milliliter', category: 'volume' },
    { value: 'L', label: 'Liter', category: 'volume' },
    { value: 'GAL', label: 'Gallon', category: 'volume' },
    { value: 'M3', label: 'Cubic Meter', category: 'volume' },
    // Length
    { value: 'M', label: 'Meter', category: 'length' },
    { value: 'FT', label: 'Foot', category: 'length' },
    { value: 'YD', label: 'Yard', category: 'length' },
    // Area
    { value: 'SQM', label: 'Square Meter', category: 'area' },
    // Energy
    { value: 'KWH', label: 'Kilowatt Hour', category: 'energy' },
    // Time
    { value: 'HR', label: 'Hour', category: 'time' },
    { value: 'DAY', label: 'Day', category: 'time' },
    { value: 'MO', label: 'Month', category: 'time' },
    { value: 'YR', label: 'Year', category: 'time' },
    // Service
    { value: 'JOB', label: 'Job / Service', category: 'service' },
    { value: 'TRIP', label: 'Trip', category: 'service' },
] as const satisfies readonly Uom[];

export type UomCode = (typeof UOMS)[number]['value'];

/** Options for the combobox: "KG · Kilogram". */
export const UOM_OPTIONS: { value: string; label: string }[] = UOMS.map((uom) => ({
    value: uom.value,
    label: `${uom.value} · ${uom.label}`,
}));

/** Full name for a stored code, or the code itself if it is unknown. */
export function uomLabel(code: string | null | undefined): string {
    if (!code) return '';

    return UOMS.find((uom) => uom.value === code)?.label ?? code;
}