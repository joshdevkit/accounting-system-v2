import { Check, ChevronsUpDown } from "lucide-react";
import { useMemo, useState } from "react";
import { cn } from "@/lib/utils";
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

export type AccountOption = {
    id: number;
    label: string;
    code?: string;
};

export type AccountGroup = {
    category: string;
    options: AccountOption[];
};

export function AccountCombobox({
    groups,
    value,
    onChange,
    disabled,
    placeholder = "Account",
    "aria-label": ariaLabel,
    className,
}: {
    groups: AccountGroup[];
    value: number | "";
    onChange: (value: number | "") => void;
    disabled?: boolean;
    placeholder?: string;
    "aria-label"?: string;
    className?: string;
}) {
    const [open, setOpen] = useState(false);

    const selected = useMemo(() => {
        for (const group of groups) {
            const match = group.options.find((option) => option.id === value);
            if (match) return match;
        }
        return null;
    }, [groups, value]);

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger
                render={
                    <Button
                        type="button"
                        variant="outline"
                        role="combobox"
                        aria-expanded={open}
                        aria-label={ariaLabel}
                        disabled={disabled}
                        className={cn(
                            "h-9 w-full justify-between font-normal",
                            !selected && "text-muted-foreground",
                            className,
                        )}
                    >
                        <span className="truncate">
                            {selected ? selected.label : placeholder}
                        </span>
                        <ChevronsUpDown className="text-muted-foreground size-4 shrink-0 opacity-50" />
                    </Button>
                }
            />
            <PopoverContent
                className="w-(--radix-popover-trigger-width) p-0"
                align="start"
            >
                <Command>
                    <CommandInput placeholder="Search accounts…" />
                    <CommandList>
                        <CommandEmpty>No accounts found.</CommandEmpty>
                        {groups.map((group) => (
                            <CommandGroup
                                key={group.category}
                                heading={group.category}
                            >
                                {group.options.map((option) => (
                                    <CommandItem
                                        key={option.id}
                                        value={option.label}
                                        onSelect={() => {
                                            onChange(option.id);
                                            setOpen(false);
                                        }}
                                    >
                                        <Check
                                            className={cn(
                                                "mr-2 size-4",
                                                option.id === value
                                                    ? "opacity-100"
                                                    : "opacity-0",
                                            )}
                                        />
                                        <span className="truncate">
                                            {option.label}
                                        </span>
                                    </CommandItem>
                                ))}
                            </CommandGroup>
                        ))}
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
