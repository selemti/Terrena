<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class PostCorteController {
    public static function create(Request $request, Response $response) {
        $sid = qp($request, 'sesion_id');
        $deE = (float)qp($request, 'declarado_efectivo_fin', 0);
        $deT = (float)qp($request, 'declarado_tarjetas_fin', 0);
        $not = qp($request, 'notas', null);

        if(!$sid) return J($response, ['ok'=>false,'error'=>'missing_params (sesion_id)'],400);

        $sysE = 0.0; $sysT = 0.0;
        try{
            $v = pdo()->prepare("SELECT * FROM selemti.vw_conciliacion_sesion WHERE sesion_id=:sid");
            $v->execute([':sid'=>$sid]);
            if($row = $v->fetch()){
                $sysE = (float)($row['sistema_efectivo_esperado'] ?? 0);
                $credit   = (float)($row['sys_credit']   ?? $row['credit']   ?? 0);
                $debit    = (float)($row['sys_debit']    ?? $row['debit']    ?? 0);
                $transfer = (float)($row['sys_transfer'] ?? $row['transfer'] ?? 0);
                $custom   = (float)($row['sys_custom']   ?? $row['custom']   ?? 0);
                $sysT = $credit + $debit + $transfer + $custom;
            }
        } catch(Throwable $e){}

        $difE = $deE - $sysE;
        $difT = $deT - $sysT;

        $ver = function(float $dif): string {
            if (abs($dif) <= DIFF_THRESHOLD) return 'CUADRA';
            return $dif > 0 ? 'A_FAVOR' : 'EN_CONTRA';
        };

        try{
            $ins = pdo()->prepare("
                INSERT INTO selemti.postcorte
                (sesion_id, sistema_efectivo_esperado, declarado_efectivo, diferencia_efectivo, veredicto_efectivo,
                 sistema_tarjetas, declarado_tarjetas, diferencia_tarjetas, veredicto_tarjetas, notas)
                VALUES
                (:sid,:sysE,:deE,:difE,:verE,:sysT,:deT,:difT,:verT,:notas)
                RETURNING id
            ");
            $ins->execute([
                ':sid'=>$sid, ':sysE'=>$sysE, ':deE'=>$deE, ':difE'=>$difE, ':verE'=>$ver($difE),
                ':sysT'=>$sysT, ':deT'=>$deT, ':difT'=>$difT, ':verT'=>$ver($difT), ':notas'=>$not
            ]);
            $pid = (int)$ins->fetch()['id'];
            return J($response, [
                'ok'=>true,'postcorte_id'=>$pid,
                'efectivo'=>['esperado'=>$sysE,'declarado'=>$deE,'dif'=>$difE,'veredicto'=>$ver($difE)],
                'tarjetas'=>['sistema'=>$sysT,'declarado'=>$deT,'dif'=>$difT,'veredicto'=>$ver($difT)]
            ]);
        } catch(Throwable $e){
            return J($response, ['ok'=>false,'error'=>'postcorte_failed','message'=>$e->getMessage()],500);
        }
    }
}
