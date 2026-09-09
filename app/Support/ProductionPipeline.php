<?php

namespace App\Support;

/**
 * The shop floor, as the production system already describes it.
 *
 * Mirrors Task::DEPARTMENTS and JobOrder::PRINT_TYPES from the Imprint
 * Production application: the ordered stages a job passes through, and the
 * route each print type takes through them. Costing follows the floor rather
 * than inventing a shape of its own, so a quotation charges for the work the
 * job will actually cause.
 *
 * Two kinds of stage:
 *
 * Per job - layout, the final mockup and template, the client's sample, and
 * releasing the finished order. These happen once however many pieces are
 * ordered, which is why ten shirts cost more each than a hundred.
 *
 * Per piece - printing, cutting, pressing, pairing, sewing, quality control
 * and putting the finished pieces into inventory. Every piece pays these.
 *
 * The batch stages (Mass production and the batch repeats of cutting, pairing,
 * sewing and QC) are the same work done for the rest of the run, so their cost
 * is already carried by the per-piece rates and they are not charged twice.
 */
class ProductionPipeline
{
    public const PER_JOB = 'per_job';

    public const PER_PIECE = 'per_piece';

    /**
     * Every stage a quotation can be charged for, in floor order.
     *
     * @return array<string, array{label: string, basis: string, sequence: int}>
     */
    public static function stages(): array
    {
        return [
            'layout' => ['label' => 'Layout', 'basis' => self::PER_JOB, 'sequence' => 1],
            'mockup' => ['label' => 'Final mockup & template', 'basis' => self::PER_JOB, 'sequence' => 2],
            'printing' => ['label' => 'Printing', 'basis' => self::PER_PIECE, 'sequence' => 3],
            'embroidery' => ['label' => 'Embroidery', 'basis' => self::PER_PIECE, 'sequence' => 4],
            'press_roller' => ['label' => 'Roller press', 'basis' => self::PER_PIECE, 'sequence' => 4],
            'press_small' => ['label' => 'Small press', 'basis' => self::PER_PIECE, 'sequence' => 4],
            'cutting_laser' => ['label' => 'Laser cutting', 'basis' => self::PER_PIECE, 'sequence' => 5],
            'cutting_manual' => ['label' => 'Manual cutting', 'basis' => self::PER_PIECE, 'sequence' => 5],
            'pairing' => ['label' => 'Pairing', 'basis' => self::PER_PIECE, 'sequence' => 6],
            'sewing' => ['label' => 'Sewing', 'basis' => self::PER_PIECE, 'sequence' => 7],
            'qc' => ['label' => 'Quality control', 'basis' => self::PER_PIECE, 'sequence' => 8],
            'sample' => ['label' => 'Sample for client', 'basis' => self::PER_JOB, 'sequence' => 9],
            'inventory' => ['label' => 'Inventory', 'basis' => self::PER_PIECE, 'sequence' => 15],
            'release' => ['label' => 'Release to client', 'basis' => self::PER_JOB, 'sequence' => 16],
        ];
    }

    /**
     * The print types, and the route each takes. Printer, cutting and press
     * follow JobOrder::PRINT_TYPES; a type with no press simply omits it.
     *
     * @return array<string, array{label: string, stages: array<int, string>}>
     */
    public static function printTypes(): array
    {
        // Every job is laid out, mocked up, sampled and released, and every
        // piece is printed, paired, sewn, checked and put away.
        $common = ['layout', 'mockup', 'printing', 'pairing', 'sewing', 'qc', 'sample', 'inventory', 'release'];

        return [
            'full_sublimation' => [
                'label' => 'Full Sublimation',
                'stages' => array_merge($common, ['cutting_laser', 'press_roller']),
            ],
            'dtf' => [
                'label' => 'DTF',
                'stages' => array_merge($common, ['cutting_manual']),
            ],
            'eco_solvent' => [
                'label' => 'Eco Solvent',
                'stages' => array_merge($common, ['cutting_manual']),
            ],
            'vinyl' => [
                'label' => 'Vinyl',
                'stages' => array_merge($common, ['cutting_manual']),
            ],
            'embroidery' => [
                'label' => 'Embroidery',
                'stages' => array_merge($common, ['cutting_manual', 'embroidery']),
            ],
            'silkscreen' => [
                'label' => 'Silkscreen',
                'stages' => array_merge($common, ['cutting_manual', 'press_small']),
            ],
        ];
    }

    /** @return array<int, string> */
    public static function keys(): array
    {
        return array_keys(self::printTypes());
    }

    public static function label(?string $printType): string
    {
        return self::printTypes()[$printType]['label'] ?? '—';
    }

    /**
     * The stages a print type runs, or none at all when it names no type.
     *
     * @return array<int, string>
     */
    public static function stagesFor(?string $printType): array
    {
        return self::printTypes()[$printType]['stages'] ?? [];
    }

    /**
     * The stages of one basis that a print type runs.
     *
     * @return array<int, string>
     */
    public static function stagesOfBasis(?string $printType, string $basis): array
    {
        $stages = self::stages();

        return array_values(array_filter(
            self::stagesFor($printType),
            fn (string $key) => ($stages[$key]['basis'] ?? null) === $basis
        ));
    }
}
