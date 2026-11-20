import { Check, ChevronsUpDown } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Input } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { useReportFilters } from '@/hooks/use-report-filters';
import type { ReportFilters, ReportPickers } from '@/types/reports';

export function ReportFilterBar({
    routeName,
    filters,
    pickers,
    extra,
}: {
    routeName: string;
    filters: ReportFilters;
    pickers: ReportPickers;
    /** Extra controls specific to one report (e.g. Trial Balance's "summary" toggle). */
    extra?: React.ReactNode;
}) {
    const {
        selectedYear,
        apply,
        selectFiscalYear,
        selectPeriod,
        reset,
    } = useReportFilters(routeName, filters, pickers);

    const selectedFiscalYear = pickers.fiscalYears.find(
        (year) => year.id === filters.fiscal_year_id,
    );

    const selectedPeriod = selectedYear?.periods.find(
        (period) => period.id === filters.accounting_period_id,
    );

    const selectedBranch = pickers.branches.find(
        (branch) => branch.id === filters.branch_id,
    );

    return (
        <Card>
            <CardContent className="flex flex-wrap items-end gap-3 p-3">
                {/* Fiscal Year */}
                <div className="space-y-1">
                    <label className="text-muted-foreground text-xs">
                        Fiscal year
                    </label>

                    <Popover>
                        <PopoverTrigger
                            type="button"
                            className="border-input bg-background hover:bg-accent hover:text-accent-foreground flex h-9 w-auto min-w-36 items-center justify-between gap-2 rounded-md border px-3 text-sm shadow-xs outline-none"
                        >
                            <span className="truncate">
                                {selectedFiscalYear
                                    ? `${selectedFiscalYear.year} (${selectedFiscalYear.status})`
                                    : 'All years'}
                            </span>

                            <ChevronsUpDown className="text-muted-foreground size-4 shrink-0" />
                        </PopoverTrigger>

                        <PopoverContent className="w-[240px] p-0">
                            <Command>
                                <CommandInput placeholder="Search fiscal year..." />

                                <CommandList>
                                    <CommandEmpty>
                                        No fiscal year found.
                                    </CommandEmpty>

                                    <CommandGroup>
                                        <CommandItem
                                            value="all years"
                                            onSelect={() => selectFiscalYear('')}
                                        >
                                            <Check
                                                className={`mr-2 size-4 ${
                                                    !filters.fiscal_year_id
                                                        ? 'opacity-100'
                                                        : 'opacity-0'
                                                }`}
                                            />
                                            All years
                                        </CommandItem>

                                        {pickers.fiscalYears.map((year) => (
                                            <CommandItem
                                                key={year.id}
                                                value={`${year.year} ${year.status}`}
                                                onSelect={() =>
                                                    selectFiscalYear(
                                                        String(year.id),
                                                    )
                                                }
                                            >
                                                <Check
                                                    className={`mr-2 size-4 ${
                                                        filters.fiscal_year_id ===
                                                        year.id
                                                            ? 'opacity-100'
                                                            : 'opacity-0'
                                                    }`}
                                                />

                                                <span>
                                                    {year.year} ({year.status})
                                                </span>
                                            </CommandItem>
                                        ))}
                                    </CommandGroup>
                                </CommandList>
                            </Command>
                        </PopoverContent>
                    </Popover>
                </div>

                {/* Period */}
                <div className="space-y-1">
                    <label className="text-muted-foreground text-xs">
                        Period
                    </label>

                    <Popover>
                        <PopoverTrigger
                            type="button"
                            disabled={!selectedYear}
                            className="border-input bg-background hover:bg-accent hover:text-accent-foreground flex h-9 w-auto min-w-40 items-center justify-between gap-2 rounded-md border px-3 text-sm shadow-xs outline-none disabled:pointer-events-none disabled:opacity-50"
                        >
                            <span className="truncate">
                                {selectedPeriod
                                    ? `${selectedPeriod.period_number}. ${selectedPeriod.name}`
                                    : selectedYear
                                      ? 'All periods'
                                      : 'Pick a year first'}
                            </span>

                            <ChevronsUpDown className="text-muted-foreground size-4 shrink-0" />
                        </PopoverTrigger>

                        <PopoverContent className="w-[280px] p-0">
                            <Command>
                                <CommandInput placeholder="Search period..." />

                                <CommandList>
                                    <CommandEmpty>
                                        No period found.
                                    </CommandEmpty>

                                    <CommandGroup>
                                        <CommandItem
                                            value="all periods"
                                            onSelect={() => selectPeriod('')}
                                        >
                                            <Check
                                                className={`mr-2 size-4 ${
                                                    !filters.accounting_period_id
                                                        ? 'opacity-100'
                                                        : 'opacity-0'
                                                }`}
                                            />
                                            All periods
                                        </CommandItem>

                                        {selectedYear?.periods.map((period) => (
                                            <CommandItem
                                                key={period.id}
                                                value={`${period.period_number} ${period.name}`}
                                                onSelect={() =>
                                                    selectPeriod(
                                                        String(period.id),
                                                    )
                                                }
                                            >
                                                <Check
                                                    className={`mr-2 size-4 ${
                                                        filters.accounting_period_id ===
                                                        period.id
                                                            ? 'opacity-100'
                                                            : 'opacity-0'
                                                    }`}
                                                />

                                                {period.period_number}.{' '}
                                                {period.name}
                                            </CommandItem>
                                        ))}
                                    </CommandGroup>
                                </CommandList>
                            </Command>
                        </PopoverContent>
                    </Popover>
                </div>

                {/* Branch */}
                <div className="space-y-1">
                    <label className="text-muted-foreground text-xs">
                        Branch
                    </label>

                    <Popover>
                        <PopoverTrigger
                            type="button"
                            className="border-input bg-background hover:bg-accent hover:text-accent-foreground flex h-9 w-auto min-w-32 items-center justify-between gap-2 rounded-md border px-3 text-sm shadow-xs outline-none"
                        >
                            <span className="truncate">
                                {selectedBranch
                                    ? selectedBranch.name
                                    : 'All branches'}
                            </span>

                            <ChevronsUpDown className="text-muted-foreground size-4 shrink-0" />
                        </PopoverTrigger>

                        <PopoverContent className="w-[280px] p-0">
                            <Command>
                                <CommandInput placeholder="Search branch..." />

                                <CommandList>
                                    <CommandEmpty>
                                        No branch found.
                                    </CommandEmpty>

                                    <CommandGroup>
                                        <CommandItem
                                            value="all branches"
                                            onSelect={() =>
                                                apply({ branch_id: null })
                                            }
                                        >
                                            <Check
                                                className={`mr-2 size-4 ${
                                                    !filters.branch_id
                                                        ? 'opacity-100'
                                                        : 'opacity-0'
                                                }`}
                                            />
                                            All branches
                                        </CommandItem>

                                        {pickers.branches.map((branch) => (
                                            <CommandItem
                                                key={branch.id}
                                                value={`${branch.name} ${branch.code}`}
                                                onSelect={() =>
                                                    apply({
                                                        branch_id: branch.id,
                                                    })
                                                }
                                            >
                                                <Check
                                                    className={`mr-2 size-4 ${
                                                        filters.branch_id ===
                                                        branch.id
                                                            ? 'opacity-100'
                                                            : 'opacity-0'
                                                    }`}
                                                />

                                                <div className="flex min-w-0 flex-col">
                                                    <span className="truncate">
                                                        {branch.name}
                                                    </span>

                                                    {branch.code && (
                                                        <span className="text-muted-foreground text-xs">
                                                            {branch.code}
                                                        </span>
                                                    )}
                                                </div>
                                            </CommandItem>
                                        ))}
                                    </CommandGroup>
                                </CommandList>
                            </Command>
                        </PopoverContent>
                    </Popover>
                </div>

                {/* From */}
                <div className="space-y-1">
                    <label
                        htmlFor="from"
                        className="text-muted-foreground text-xs"
                    >
                        From
                    </label>

                    <Input
                        id="from"
                        type="date"
                        className="w-auto"
                        defaultValue={filters.from ?? ''}
                        onBlur={(event) =>
                            apply({
                                from: event.target.value || null,
                            })
                        }
                    />
                </div>

                {/* To */}
                <div className="space-y-1">
                    <label
                        htmlFor="to"
                        className="text-muted-foreground text-xs"
                    >
                        To
                    </label>

                    <Input
                        id="to"
                        type="date"
                        className="w-auto"
                        defaultValue={filters.to ?? ''}
                        onBlur={(event) =>
                            apply({
                                to: event.target.value || null,
                            })
                        }
                    />
                </div>

                <Button
                    variant="ghost"
                    size="sm"
                    type="button"
                    onClick={reset}
                >
                    Reset
                </Button>

                {extra && (
                    <div className="ml-auto flex items-center gap-2">
                        {extra}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
