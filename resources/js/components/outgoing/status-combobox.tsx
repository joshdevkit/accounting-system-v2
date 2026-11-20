import { Check, ChevronsUpDown } from "lucide-react";
import { Button } from "@/components/ui/button";
import {
  Command,
  CommandGroup,
  CommandItem,
  CommandList,
} from "@/components/ui/command";
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover";
import type { ProcessingStatus } from "./types";

const STATUS_LABELS: Record<ProcessingStatus, string> = {
  pending: "Pending",
  for_cheque: "For Cheque",
  for_release: "For Release",
  for_voucher: "For Voucher",
  released: "Released",
  cancelled: "Cancelled",
};

const NEXT_STATUSES: Record<ProcessingStatus, ProcessingStatus[]> = {
  pending: ["pending", "for_cheque", "cancelled"],
  for_cheque: ["for_cheque", "for_release", "pending", "cancelled"],
  for_release: ["for_release", "released", "pending", "cancelled"],
  for_voucher: ["for_voucher", "for_cheque", "cancelled"],
  released: [],
  cancelled: [],
};

interface StatusComboboxProps {
  value: ProcessingStatus;
  onChange: (next: ProcessingStatus) => void;
}

function optionLabel(option: ProcessingStatus, current: ProcessingStatus) {
  if (option === "pending" && current !== "pending") return "Cancel payment";
  if (option === "cancelled") return "Void purchase";
  if (option === "for_voucher" && current !== "for_voucher") return "Move back to Pending";
  return STATUS_LABELS[option];
}

export function StatusCombobox({ value, onChange }: StatusComboboxProps) {
  if (value === "released" || value === "cancelled") {
    return (
      <span className="text-muted-foreground text-xs">
        {STATUS_LABELS[value]}
      </span>
    );
  }

  return (
    <Popover>
      <PopoverTrigger
        render={
          <Button
            type="button"
            variant="outline"
            size="sm"
            role="combobox"
            className="w-36 justify-between font-normal"
          />
        }
      >
        {STATUS_LABELS[value]}
        <ChevronsUpDown className="ml-2 size-3.5 shrink-0 opacity-50" />
      </PopoverTrigger>
      <PopoverContent className="w-44 p-0">
        <Command>
          <CommandList>
            <CommandGroup>
              {NEXT_STATUSES[value].map((option) => (
                <CommandItem
                  key={option}
                  value={optionLabel(option, value)}
                  onSelect={() => onChange(option)}
                >
                  <Check
                    className={
                      option === value
                        ? "mr-2 size-4 opacity-100"
                        : "mr-2 size-4 opacity-0"
                    }
                  />
                  {optionLabel(option, value)}
                </CommandItem>
              ))}
            </CommandGroup>
          </CommandList>
        </Command>
      </PopoverContent>
    </Popover>
  );
}

