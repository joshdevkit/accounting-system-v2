import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from "@/components/ui/alert-dialog"
import { Button } from "@/components/ui/button"
import { Trash2 } from "lucide-react"

interface DeleteDialogProps {
    referenceNo: string
    onConfirm: () => void
}

export function DeleteDialog({
    referenceNo,
    onConfirm,
}: DeleteDialogProps) {
    return (
        <AlertDialog>
            <AlertDialogTrigger
                render={
                    <Button
                        variant="ghost"
                        size="icon"
                        className="text-destructive hover:text-destructive"
                    />
                }
            >
                <Trash2 className="size-4" />
                <span className="sr-only">
                    Delete draft {referenceNo}
                </span>
            </AlertDialogTrigger>

            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>
                        Delete draft?
                    </AlertDialogTitle>

                    <AlertDialogDescription>
                        Are you sure you want to delete draft{" "}
                        <span className="font-medium text-foreground">
                            {referenceNo}
                        </span>
                        ?
                        <span className="mt-2 block">
                            This action cannot be undone.
                        </span>
                    </AlertDialogDescription>
                </AlertDialogHeader>

                <AlertDialogFooter>
                    <AlertDialogCancel>
                        Cancel
                    </AlertDialogCancel>

                    <AlertDialogAction
                        variant="destructive"
                        onClick={onConfirm}
                    >
                        Delete draft
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    )
}