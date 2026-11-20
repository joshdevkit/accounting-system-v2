import { useState } from "react";
import { Check, ChevronsUpDown } from "lucide-react";

import { Button } from "@/components/ui/button";
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from "@/components/ui/command";
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/components/ui/popover";
import { UOM_OPTIONS } from "@/lib/uom";

/**
 * Searchable unit-of-measure picker for a purchase line.
 * Search matches the code or the name ("kg", "kilo"). Picking the selected
 * unit again clears it.
 */
export function UomPicker({
    value,
    onChange,
    disabled,
    label,
}: {
    value: string;
    onChange: (value: string) => void;
    disabled?: boolean;
    label?: string;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger
                render={
                    <Button
                        type="button"
                        variant="outline"
                        role="combobox"
                        aria-label={label}
                        disabled={disabled}
                        className="h-9 w-full min-w-24 justify-between"
                    />
                }
            >
                {value || <span className="text-muted-foreground">UOM</span>}

                <ChevronsUpDown className="ml-2 size-4 shrink-0 opacity-50" />
            </PopoverTrigger>

            <PopoverContent className="w-56 p-0">
                <Command>
                    <CommandInput placeholder="Search unit…" />

                    <CommandList>
                        <CommandEmpty>No unit found.</CommandEmpty>

                        <CommandGroup>
                            {UOM_OPTIONS.map((option) => (
                                <CommandItem
                                    key={option.value}
                                    value={option.label}
                                    onSelect={() => {
                                        onChange(
                                            option.value === value
                                                ? ""
                                                : option.value,
                                        );
                                        setOpen(false);
                                    }}
                                >
                                    <Check
                                        className={
                                            option.value === value
                                                ? "mr-2 size-4 opacity-100"
                                                : "mr-2 size-4 opacity-0"
                                        }
                                    />
                                    {option.label}
                                </CommandItem>
                            ))}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}